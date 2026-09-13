<?php
header('Content-Type: application/json');
date_default_timezone_set('Asia/Shanghai');

$tmpDir = '/tmp/ffmpeg_preview/';
if (!file_exists($tmpDir)) {
    mkdir($tmpDir, 0777, true);
}

// 预设保存逻辑
if (isset($_GET['action']) && $_GET['action'] === 'save_preset') {
    $data = json_decode(file_get_contents('php://input'), true);
    $presetsFile = __DIR__ . '/../presets.json';
    $presets = [];
    if (file_exists($presetsFile)) {
        $presets = json_decode(file_get_contents($presetsFile), true);
    }
    $presets[] = $data;
    file_put_contents($presetsFile, json_encode($presets, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo json_encode(['success' => true]);
    exit;
}

// 主处理逻辑
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_FILES['file'])) {
            throw new Exception('没有上传文件');
        }

        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('文件上传错误: ' . $file['error']);
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $allowedExts = ['mp4', 'mov', 'mkv', 'jpg', 'jpeg', 'png', 'webp'];
        if (!in_array(strtolower($ext), $allowedExts)) {
            throw new Exception('不支持的文件格式');
        }

        $filename = md5($file['name'] . time());
        $inputPath = $tmpDir . $filename . '_in.' . $ext;
        $outputPath = $tmpDir . $filename . '_out.' . $ext;

        if (!move_uploaded_file($file['tmp_name'], $inputPath)) {
            throw new Exception('无法保存上传的文件');
        }

        // 获取参数并过滤
        $brightness = filter_var($_POST['brightness'] ?? 0, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $contrast = filter_var($_POST['contrast'] ?? 1, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $saturation = filter_var($_POST['saturation'] ?? 1, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $temp = filter_var($_POST['temp'] ?? 6500, FILTER_SANITIZE_NUMBER_INT);
        $unsharp_val = filter_var($_POST['unsharp'] ?? 0, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $blur_val = filter_var($_POST['blur'] ?? 0, FILTER_SANITIZE_NUMBER_INT);
        // 构建滤镜字符串
        $filters = [];

        // eq:亮度、对比度、饱和度
        $filters[] = "eq=brightness={$brightness}:contrast={$contrast}:saturation={$saturation}";

        // 色温
        if ($temp != 6500) {
            $filters[] = "colortemperature=temperature={$temp}";
        }

        // 锐化
        if ($unsharp_val > 0) {
            $filters[] = "unsharp=5:5:{$unsharp_val}";
        }

        // 模糊
        if ($blur_val > 0) {
            $filters[] = "boxblur={$blur_val}";
        }

        $vf = implode(',', array_filter($filters));

        // 限制处理时长 (如果是视频，只处理前 5 秒作为预览)
        $limit = "";
        $isVideo = strpos($file['type'], 'video/') !== false;
        if ($isVideo) {
            $limit = "-t 5"; // 15s 限制太长，快速调试 5s 足够
        }

        $ffmpeg = getenv('FFMPEG_PATH') ?: 'ffmpeg';

        // 安全地构建命令
        // 使用 timeout 限制执行时间
        $cmd = sprintf(
            "timeout 15 %s -y -i %s -vf %s %s %s 2>&1",
            escapeshellarg($ffmpeg),
            escapeshellarg($inputPath),
            escapeshellarg($vf),
            $limit,
            escapeshellarg($outputPath)
        );

        $startTime = microtime(true);
        exec($cmd, $output, $returnCode);
        $duration = microtime(true) - $startTime;

        if ($returnCode !== 0) {
            throw new Exception("FFmpeg 执行失败 (Return: $returnCode): " . implode("\n", $output));
        }

        if (!file_exists($outputPath)) {
            throw new Exception("FFmpeg 执行成功但未发现输出文件。命令可能被中断或参数错误。");
        }

        // ===== 采集原始素材信息 (ffprobe)，用于调参报告 =====
        $ffprobe = getenv('FFPROBE_PATH') ?: 'ffprobe';
        $probeCmd = sprintf(
            '%s -v quiet -print_format json -show_format -show_streams %s',
            escapeshellarg($ffprobe),
            escapeshellarg($inputPath)
        );
        $probeOut = [];
        $probeCode = 0;
        exec($probeCmd, $probeOut, $probeCode);

        $mediaInfo = [
            'filename' => $file['name'],
            'size'     => (int)$file['size'],
            'type'     => $isVideo ? 'video' : 'image',
        ];
        if ($probeCode === 0) {
            $probe = json_decode(implode('', $probeOut), true);
            if (is_array($probe)) {
                $fmt = $probe['format'] ?? [];
                $mediaInfo['format']   = $fmt['format_name'] ?? null;
                $mediaInfo['duration'] = isset($fmt['duration']) ? round((float)$fmt['duration'], 3) : null;
                $mediaInfo['bitrate']  = isset($fmt['bit_rate']) ? (int)$fmt['bit_rate'] : null;

                foreach (($probe['streams'] ?? []) as $stream) {
                    $codecType = $stream['codec_type'] ?? '';
                    if ($codecType === 'video' && !isset($mediaInfo['video'])) {
                        $fps = null;
                        if (!empty($stream['r_frame_rate']) && strpos($stream['r_frame_rate'], '/') !== false) {
                            [$num, $den] = array_map('floatval', explode('/', $stream['r_frame_rate'], 2));
                            if ($den > 0) {
                                $fps = round($num / $den, 2);
                            }
                        }
                        $mediaInfo['video'] = [
                            'codec'   => $stream['codec_name'] ?? null,
                            'width'   => $stream['width'] ?? null,
                            'height'  => $stream['height'] ?? null,
                            'fps'     => $fps,
                            'pix_fmt' => $stream['pix_fmt'] ?? null,
                        ];
                    }
                    if ($codecType === 'audio' && !isset($mediaInfo['audio'])) {
                        $mediaInfo['audio'] = [
                            'codec'       => $stream['codec_name'] ?? null,
                            'sample_rate' => isset($stream['sample_rate']) ? (int)$stream['sample_rate'] : null,
                            'channels'    => $stream['channels'] ?? null,
                        ];
                    }
                }
            }
        }

        // ===== 生成处理后效果的缩略预览图 (供报告内嵌，限制尺寸避免报告体积过大) =====
        $thumbUrl = null;
        $thumbPath = $tmpDir . $filename . '_thumb.jpg';
        $thumbCmd = sprintf(
            "timeout 10 %s -y -i %s -frames:v 1 -vf %s -q:v 4 %s 2>&1",
            escapeshellarg($ffmpeg),
            escapeshellarg($outputPath),
            escapeshellarg("scale='min(640,iw)':-2"),
            escapeshellarg($thumbPath)
        );
        $thumbOut = [];
        $thumbCode = 0;
        exec($thumbCmd, $thumbOut, $thumbCode);
        if ($thumbCode === 0 && file_exists($thumbPath)) {
            $thumbUrl = '/preview/' . basename($thumbPath);
        }

        echo json_encode([
            'success'      => true,
            'url'          => '/preview/' . basename($outputPath),
            'cmd'          => $cmd,
            'duration'     => $duration,
            'vf'           => $vf,
            'thumb'        => $thumbUrl,
            'media'        => $mediaInfo,
            'params'       => [
                'brightness' => $brightness,
                'contrast'   => $contrast,
                'saturation' => $saturation,
                'temp'       => $temp,
                'unsharp'    => $unsharp_val,
                'blur'       => $blur_val,
            ],
            'generated_at' => date('Y-m-d H:i:s'),
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}
