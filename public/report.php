<?php
/**
 * 调参报告导出接口
 *
 * 接收前端提交的调参结果（素材信息 / 滤镜参数 / 命令行 / 缩略图引用），
 * 生成 纯文本(txt) 或 自包含 HTML 报告，并以附件形式触发下载。
 *
 * 注意：报告仅内嵌小尺寸 JPEG 预览缩略图（base64），绝不嵌入视频文件，
 * 以保证报告体积足够小，方便直接发给剪辑同事复现效果。
 */
date_default_timezone_set('Asia/Shanghai');

$tmpDir = '/tmp/ffmpeg_preview/';

// 仅接受 POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => '仅支持 POST 请求']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => '请求数据格式错误']);
    exit;
}

$format = $data['format'] ?? 'html';
if (!in_array($format, ['html', 'txt'], true)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => '不支持的报告格式（仅支持 html / txt）']);
    exit;
}

/* ---------------- 工具函数 ---------------- */

/** HTML 转义 */
function h($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/** 限制字符串长度，防止异常超大 payload */
function clip($v, int $max = 4000): string
{
    $v = trim((string)$v);
    return mb_strlen($v) > $max ? mb_substr($v, 0, $max) . '…' : $v;
}

/** 空值占位 */
function valOrDash($v): string
{
    return ($v === null || $v === '') ? '—' : (string)$v;
}

/** 人性化文件大小 */
function humanSize($bytes): string
{
    $bytes = (float)$bytes;
    if ($bytes <= 0) {
        return '—';
    }
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = min((int)floor(log($bytes, 1024)), count($units) - 1);
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}

/** 人性化时长 */
function humanDuration($seconds): string
{
    if ($seconds === null || $seconds === '' || !is_numeric($seconds)) {
        return '—';
    }
    $seconds = (float)$seconds;
    $hh = (int)floor($seconds / 3600);
    $mm = (int)floor(fmod($seconds, 3600) / 60);
    $ss = $seconds - $hh * 3600 - $mm * 60;
    return $hh > 0
        ? sprintf('%d:%02d:%05.2f', $hh, $mm, $ss)
        : sprintf('%d:%05.2f', $mm, $ss);
}

/** 人性化码率 */
function humanBitrate($bps): string
{
    if (!is_numeric($bps) || $bps <= 0) {
        return '—';
    }
    return round($bps / 1000, 1) . ' kb/s';
}

/* ---------------- 数据整理与校验 ---------------- */

$media  = is_array($data['media'] ?? null) ? $data['media'] : [];
$params = is_array($data['params'] ?? null) ? $data['params'] : [];

$cmd         = clip($data['cmd'] ?? '', 4000);
$vf          = clip($data['vf'] ?? '', 2000);
$generatedAt = clip($data['generated_at'] ?? '', 40) ?: date('Y-m-d H:i:s');
$renderCost  = isset($data['duration']) && is_numeric($data['duration'])
    ? round((float)$data['duration'], 2)
    : null;

// 素材信息行
$videoInfo = is_array($media['video'] ?? null) ? $media['video'] : [];
$audioInfo = is_array($media['audio'] ?? null) ? $media['audio'] : [];
$isVideo   = ($media['type'] ?? '') === 'video';

$resolution = '—';
if (!empty($videoInfo['width']) && !empty($videoInfo['height'])) {
    $resolution = (int)$videoInfo['width'] . ' × ' . (int)$videoInfo['height'];
}

$mediaRows = [
    '文件名'   => clip($media['filename'] ?? '', 255) ?: '—',
    '素材类型' => $isVideo ? '视频' : '图片',
    '文件大小' => humanSize($media['size'] ?? 0),
    '封装格式' => valOrDash($media['format'] ?? null),
    '分辨率'   => $resolution,
];
if ($isVideo) {
    $mediaRows['时长']     = humanDuration($media['duration'] ?? null);
    $mediaRows['帧率']     = !empty($videoInfo['fps']) ? $videoInfo['fps'] . ' fps' : '—';
    $mediaRows['视频编码'] = valOrDash($videoInfo['codec'] ?? null);
    $mediaRows['像素格式'] = valOrDash($videoInfo['pix_fmt'] ?? null);
    $mediaRows['音频编码'] = valOrDash($audioInfo['codec'] ?? null);
    $mediaRows['整体码率'] = humanBitrate($media['bitrate'] ?? null);
}

// 滤镜参数行
$tempDisplay = valOrDash($params['temp'] ?? '6500');
if ($tempDisplay !== '—') {
    $tempDisplay .= ' K';
}
$paramRows = [
    '亮度 (Brightness)'   => valOrDash($params['brightness'] ?? '0'),
    '对比度 (Contrast)'      => valOrDash($params['contrast'] ?? '1'),
    '饱和度 (Saturation)' => valOrDash($params['saturation'] ?? '1'),
    '色温 (Temperature)'  => $tempDisplay,
    '锐化强度 (Unsharp)'   => valOrDash($params['unsharp'] ?? '0'),
    '模糊半径 (BoxBlur)'   => valOrDash($params['blur'] ?? '0'),
];

// 预览缩略图：严格校验文件名（防目录穿越），仅读取 tmp 目录下工具生成的缩略图
$thumbDataUri = null;
$thumbName = basename((string)($data['thumb'] ?? ''));
if ($thumbName !== '' && preg_match('/^[a-f0-9]{32}_thumb\.jpg$/', $thumbName)) {
    $thumbPath = $tmpDir . $thumbName;
    if (is_file($thumbPath)) {
        $thumbSize = filesize($thumbPath);
        // 防御：缩略图正常应远小于 2MB，超出则视为异常文件，不予内嵌
        if ($thumbSize !== false && $thumbSize > 0 && $thumbSize <= 2 * 1024 * 1024) {
            $thumbDataUri = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($thumbPath));
        }
    }
}

/* ---------------- 报告生成 ---------------- */

$reportTime = date('Y-m-d H:i:s');
$filename   = 'filter_report_' . date('Ymd_His') . '.' . $format;

if ($format === 'txt') {
    // ---- 纯文本报告 ----
    $lines = [];
    $lines[] = '================================================================';
    $lines[] = '  FFmpeg 滤镜调参报告';
    $lines[] = '  生成时间: ' . $generatedAt . ' (北京时间)';
    $lines[] = '================================================================';
    $lines[] = '';
    $lines[] = '[原始素材信息]';
    foreach ($mediaRows as $k => $v) {
        $lines[] = '  ' . $k . ': ' . $v;
    }
    $lines[] = '';
    $lines[] = '[滤镜参数]';
    foreach ($paramRows as $k => $v) {
        $lines[] = '  ' . $k . ': ' . $v;
    }
    $lines[] = '';
    $lines[] = '完整滤镜链 (-vf):';
    $lines[] = '  ' . ($vf !== '' ? $vf : '—');
    $lines[] = '';
    $lines[] = '[复现命令 FFmpeg]';
    $lines[] = '  ' . ($cmd !== '' ? $cmd : '—');
    $lines[] = '';
    $lines[] = '提示: 以上命令在工具容器内执行，路径为临时路径。复现时请将输入/输出';
    $lines[] = '路径替换为本地实际路径；视频预览默认仅处理前 5 秒 (-t 5)，正式出片时';
    $lines[] = '去掉该参数即可。';
    $lines[] = '';
    $lines[] = '[预览效果图]';
    $lines[] = '  纯文本报告无法内嵌图片，请改用 HTML 格式导出报告以查看处理后预览图。';
    $lines[] = '';
    $lines[] = '[其他信息]';
    $lines[] = '  预览渲染耗时: ' . ($renderCost !== null ? $renderCost . ' s（视频仅处理前 5 秒）' : '—');
    $lines[] = '  报告导出时间: ' . $reportTime;
    $lines[] = '';
    $lines[] = '----------------------------------------------------------------';
    $lines[] = '  Generated by FFmpeg Filter Lab | 本报告不包含任何视频文件';
    $lines[] = '';

    $report = implode("\n", $lines);

    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');
    echo $report;
    exit;
}

// ---- HTML 报告（自包含，无外部依赖，可离线打开） ----
$mediaRowsHtml = '';
foreach ($mediaRows as $k => $v) {
    $mediaRowsHtml .= '            <tr><td class="k">' . h($k) . '</td><td class="v">' . h($v) . "</td></tr>\n";
}
$paramRowsHtml = '';
foreach ($paramRows as $k => $v) {
    $paramRowsHtml .= '            <tr><td class="k">' . h($k) . '</td><td class="v">' . h($v) . "</td></tr>\n";
}

$previewHtml = $thumbDataUri !== null
    ? '<img class="preview" src="' . $thumbDataUri . '" alt="处理后预览图">'
    : '<p class="note">预览缩略图已过期或不可用（临时文件可能已被清理），可重新生成预览后再导出报告。</p>';

$renderCostText = $renderCost !== null ? $renderCost . ' s（视频预览仅处理前 5 秒）' : '—';

// 模板中需要转义的变量统一在 heredoc 之前处理好
$generatedAtEscaped = h($generatedAt);
$vfEscaped          = h($vf !== '' ? $vf : '—');
$cmdEscaped         = h($cmd !== '' ? $cmd : '—');

$html = <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FFmpeg 滤镜调参报告 - {$generatedAtEscaped}</title>
<style>
  :root { color-scheme: dark; }
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: -apple-system, "PingFang SC", "Microsoft YaHei", "Segoe UI", sans-serif;
         background: #0f172a; color: #e2e8f0; padding: 40px 16px; line-height: 1.6; }
  .container { max-width: 860px; margin: 0 auto; }
  .card { background: #1e293b; border: 1px solid rgba(255,255,255,.08); border-radius: 16px;
          padding: 28px; margin-bottom: 24px; }
  h1 { font-size: 26px; background: linear-gradient(90deg,#38bdf8,#818cf8);
       -webkit-background-clip: text; background-clip: text; color: transparent; }
  .meta { color: #94a3b8; font-size: 13px; margin-top: 6px; }
  .badge { display: inline-block; background: rgba(56,189,248,.12); color: #38bdf8;
           border: 1px solid rgba(56,189,248,.3); font-size: 11px; padding: 2px 10px;
           border-radius: 999px; margin-left: 8px; vertical-align: middle; }
  h2 { font-size: 15px; color: #38bdf8; letter-spacing: .08em; margin-bottom: 16px;
       padding-bottom: 10px; border-bottom: 1px solid rgba(255,255,255,.08); }
  table { width: 100%; border-collapse: collapse; font-size: 14px; }
  td { padding: 9px 4px; border-bottom: 1px solid rgba(255,255,255,.05); vertical-align: top; }
  tr:last-child td { border-bottom: none; }
  td.k { color: #94a3b8; width: 180px; }
  td.v { color: #f1f5f9; font-weight: 500; word-break: break-all; }
  img.preview { width: 100%; border-radius: 12px; border: 1px solid rgba(255,255,255,.1); display: block; }
  pre { background: #020617; border: 1px solid rgba(255,255,255,.08); border-radius: 10px;
        padding: 16px; font-family: "SF Mono", Menlo, Consolas, monospace; font-size: 12.5px;
        color: #7dd3fc; white-space: pre-wrap; word-break: break-all; }
  .note { color: #64748b; font-size: 12px; margin-top: 10px; }
  footer { text-align: center; color: #475569; font-size: 12px; padding: 8px 0; }
</style>
</head>
<body>
<div class="container">
  <div class="card">
    <h1>FFmpeg 滤镜调参报告</h1>
    <p class="meta">生成时间：{$generatedAtEscaped}（北京时间）<span class="badge">FFmpeg Filter Lab</span></p>
  </div>

  <div class="card">
    <h2>📁 原始素材信息</h2>
    <table>
{$mediaRowsHtml}    </table>
  </div>

  <div class="card">
    <h2>🎛 滤镜参数</h2>
    <table>
{$paramRowsHtml}    </table>
    <h2 style="margin-top:24px">完整滤镜链 (-vf)</h2>
    <pre>{$vfEscaped}</pre>
  </div>

  <div class="card">
    <h2>🖼 处理后预览</h2>
    {$previewHtml}
  </div>

  <div class="card">
    <h2>⌨ 复现命令</h2>
    <pre>{$cmdEscaped}</pre>
    <p class="note">提示：以上命令在工具容器内执行，路径为临时路径。复现时请将输入 / 输出路径替换为本地实际路径；视频预览默认仅处理前 5 秒（-t 5），正式出片时去掉该参数即可。</p>
  </div>

  <div class="card">
    <h2>⏱ 其他信息</h2>
    <table>
      <tr><td class="k">预览渲染耗时</td><td class="v">{$renderCostText}</td></tr>
      <tr><td class="k">报告导出时间</td><td class="v">{$reportTime}</td></tr>
    </table>
  </div>

  <footer>Generated by FFmpeg Filter Lab · 本报告仅内嵌压缩预览图，不包含任何视频文件</footer>
</div>
</body>
</html>
HTML;

header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');
echo $html;
