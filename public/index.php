<?php
$presetsFile = __DIR__ . '/../presets.json';
$presets = [];
if (file_exists($presetsFile)) {
    $presets = json_decode(file_get_contents($presetsFile), true);
}
?>
<!DOCTYPE html>
<html lang="zh-CN" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FFmpeg 滤镜调参工具</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap');

        body {
            font-family: 'Outfit', sans-serif;
        }

        .glass {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .input-range {
            -webkit-appearance: none;
            width: 100%;
            height: 6px;
            background: #334155;
            border-radius: 5px;
            outline: none;
            transition: background 0.2s;
        }

        .input-range::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 18px;
            height: 18px;
            background: #38bdf8;
            cursor: pointer;
            border-radius: 50%;
            border: 3px solid #0f172a;
        }

        /* Skeleton Animation */
        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: .5;
            }
        }

        .animate-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
    </style>
</head>

<body class="bg-slate-950 text-slate-200 min-h-screen">

    <div class="max-w-[1600px] mx-auto p-6">
        <!-- Header -->
        <header class="flex justify-between items-center mb-8">
            <div>
                <h1
                    class="text-3xl font-bold bg-gradient-to-r from-sky-400 to-indigo-500 bg-clip-text text-transparent">
                    FFmpeg Filter Lab</h1>
                <p class="text-slate-400 text-sm mt-1">快速调试滤镜参数，打造你的专属风格</p>
            </div>
            <div class="flex gap-4">
                <button onclick="resetParams()"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 transition-colors">
                    <i data-lucide="rotate-ccw" class="w-4 h-4"></i> 重置
                </button>
                <button onclick="savePreset()"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 transition-colors">
                    <i data-lucide="save" class="w-4 h-4"></i> 保存为预设
                </button>
            </div>
        </header>

        <div class="grid grid-cols-12 gap-8">
            <!-- Left Panel: Controls -->
            <div class="col-span-12 lg:col-span-4 space-y-6">
                <!-- Upload Area -->
                <section class="glass p-6 rounded-2xl shadow-xl">
                    <h2 class="flex items-center gap-2 text-lg font-semibold mb-4 text-sky-400">
                        <i data-lucide="upload-cloud" class="w-5 h-5"></i> 资源输入
                    </h2>
                    <div id="drop-area"
                        class="border-2 border-dashed border-slate-700 rounded-xl p-8 text-center hover:border-sky-500 transition-all cursor-pointer group">
                        <input type="file" id="file-input" class="hidden" accept="video/*,image/*">
                        <i data-lucide="film"
                            class="w-10 h-10 mx-auto mb-3 text-slate-500 group-hover:text-sky-400 transition-colors"></i>
                        <p class="text-slate-300">拖拽或点击上传视频/图片</p>
                        <p class="text-slate-500 text-xs mt-2">支持 MP4, MOV, JPG, PNG (最大 50MB)</p>
                    </div>
                    <div id="file-info" class="mt-4 hidden text-sm text-slate-400">
                        已选择: <span id="filename" class="text-sky-400 font-medium"></span>
                    </div>
                </section>

                <!-- Filters Area -->
                <section class="glass p-6 rounded-2xl shadow-xl space-y-6">
                    <h2 class="flex items-center gap-2 text-lg font-semibold mb-4 text-sky-400">
                        <i data-lucide="sliders" class="w-5 h-5"></i> 滤镜参数
                    </h2>

                    <div class="space-y-4">
                        <!-- Brightness -->
                        <div class="space-y-2">
                            <div class="flex justify-between items-center text-sm">
                                <span>亮度 (Brightness)</span>
                                <input type="number" id="val-brightness" step="0.01"
                                    class="bg-slate-800 border-none rounded px-2 py-0.5 w-16 text-right outline-none">
                            </div>
                            <input type="range" id="range-brightness" min="-1" max="1" step="0.01" value="0"
                                class="input-range">
                        </div>

                        <!-- Contrast -->
                        <div class="space-y-2">
                            <div class="flex justify-between items-center text-sm">
                                <span>对比度 (Contrast)</span>
                                <input type="number" id="val-contrast" step="0.01"
                                    class="bg-slate-800 border-none rounded px-2 py-0.5 w-16 text-right outline-none">
                            </div>
                            <input type="range" id="range-contrast" min="0" max="2" step="0.01" value="1"
                                class="input-range">
                        </div>

                        <!-- Saturation -->
                        <div class="space-y-2">
                            <div class="flex justify-between items-center text-sm">
                                <span>饱和度 (Saturation)</span>
                                <input type="number" id="val-saturation" step="0.01"
                                    class="bg-slate-800 border-none rounded px-2 py-0.5 w-16 text-right outline-none">
                            </div>
                            <input type="range" id="range-saturation" min="0" max="3" step="0.01" value="1"
                                class="input-range">
                        </div>

                        <!-- Color Temp -->
                        <div class="space-y-2">
                            <div class="flex justify-between items-center text-sm">
                                <span>色温 (Temperature)</span>
                                <input type="number" id="val-temp" step="1"
                                    class="bg-slate-800 border-none rounded px-2 py-0.5 w-16 text-right outline-none">
                            </div>
                            <input type="range" id="range-temp" min="1000" max="40000" step="100" value="6500"
                                class="input-range">
                        </div>

                        <!-- Unsharp (Quick Sharpen) -->
                        <div class="space-y-2">
                            <div class="flex justify-between items-center text-sm">
                                <span>锐化强度 (Unsharp)</span>
                                <span class="text-xs text-slate-500">luma_amount</span>
                            </div>
                            <div class="flex gap-4 items-center">
                                <input type="range" id="range-unsharp" min="0" max="5" step="0.1" value="0"
                                    class="input-range flex-1">
                                <span id="val-unsharp-display" class="w-8 text-right text-xs">0</span>
                            </div>
                        </div>

                        <!-- BoxBlur -->
                        <div class="space-y-2">
                            <div class="flex justify-between items-center text-sm">
                                <span>模糊半径 (BoxBlur)</span>
                                <span class="text-xs text-slate-500">radius</span>
                            </div>
                            <div class="flex gap-4 items-center">
                                <input type="range" id="range-blur" min="0" max="20" step="1" value="0"
                                    class="input-range flex-1">
                                <span id="val-blur-display" class="w-8 text-right text-xs">0</span>
                            </div>
                        </div>


                    </div>

                    <button id="generate-btn"
                        class="w-full bg-gradient-to-r from-sky-500 to-indigo-600 text-white font-bold py-4 rounded-xl shadow-lg shadow-sky-500/20 hover:shadow-sky-500/40 hover:scale-[1.02] active:scale-95 transition-all flex justify-center items-center gap-2">
                        <i data-lucide="play" class="w-5 h-5"></i> 生成预览效果
                    </button>
                </section>

                <!-- Presets -->
                <section class="glass p-6 rounded-2xl shadow-xl">
                    <h2 class="flex items-center gap-2 text-lg font-semibold mb-4 text-sky-400">
                        <i data-lucide="bookmark" class="w-5 h-5"></i> 预设库
                    </h2>
                    <div class="grid grid-cols-2 gap-3" id="presets-list">
                        <?php foreach ($presets as $idx => $p): ?>
                            <button onclick='loadPreset(<?php echo json_encode($p["params"]); ?>)'
                                class="px-3 py-2 text-xs rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-300 transition-all text-left truncate">
                                <?php echo htmlspecialchars($p['name']); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>

            <!-- Right Panel: Previews -->
            <div class="col-span-12 lg:col-span-8 space-y-6">
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                    <!-- Original -->
                    <div class="space-y-4">
                        <h3 class="flex items-center gap-2 text-sm font-medium text-slate-400">
                            <span class="w-2 h-2 rounded-full bg-slate-500"></span> 原始素材
                        </h3>
                        <div
                            class="aspect-video bg-slate-900 rounded-2xl overflow-hidden border border-slate-800 flex items-center justify-center relative group">
                            <div id="original-placeholder" class="text-slate-700 flex flex-col items-center">
                                <i data-lucide="image" class="w-12 h-12 mb-2"></i>
                                <p class="text-sm">暂未上传素材</p>
                            </div>
                            <video id="original-video" class="w-full h-full object-contain hidden" controls></video>
                            <img id="original-image" class="w-full h-full object-contain hidden">
                        </div>
                    </div>

                    <!-- Processed -->
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <h3 class="flex items-center gap-2 text-sm font-medium text-sky-400">
                                <span class="w-2 h-2 rounded-full bg-sky-500 animate-pulse"></span> 处理后效果
                            </h3>
                            <a id="download-btn"
                                class="hidden flex items-center gap-1 text-xs bg-sky-500/10 hover:bg-sky-500/20 text-sky-400 px-2 py-1 rounded transition-colors"
                                download>
                                <i data-lucide="download" class="w-3 h-3"></i> 下载到本地
                            </a>
                        </div>
                        <div
                            class="aspect-video bg-slate-900 rounded-2xl overflow-hidden border border-slate-800 flex items-center justify-center relative">
                            <div id="processed-placeholder" class="text-slate-700 flex flex-col items-center">
                                <i data-lucide="sparkles" class="w-12 h-12 mb-2"></i>
                                <p class="text-sm">点击“生成预览”查看效果</p>
                            </div>
                            <div id="loading-overlay"
                                class="absolute inset-0 bg-slate-950/80 hidden items-center justify-center z-10">
                                <div class="flex flex-col items-center">
                                    <div
                                        class="w-12 h-12 border-4 border-sky-500/30 border-t-sky-500 rounded-full animate-spin">
                                    </div>
                                    <p class="mt-4 text-sky-400 text-sm font-medium">FFmpeg 构建中...</p>
                                </div>
                            </div>
                            <video id="processed-video" class="w-full h-full object-contain hidden" controls></video>
                            <img id="processed-image" class="w-full h-full object-contain hidden">
                        </div>
                    </div>
                </div>

                <!-- Console & Stats -->
                <section class="glass p-6 rounded-2xl shadow-xl space-y-4">
                    <div class="flex justify-between items-center">
                        <h2
                            class="flex items-center gap-2 text-sm font-semibold text-slate-400 uppercase tracking-wider">
                            <i data-lucide="terminal" class="w-4 h-4"></i> 控制台输出
                        </h2>
                        <span id="exec-time" class="text-xs text-sky-400 font-mono"></span>
                    </div>
                    <div
                        class="bg-slate-950 rounded-lg p-4 font-mono text-xs leading-relaxed border border-slate-800 overflow-x-auto min-h-[120px]">
                        <p class="text-slate-500 mb-2">// 实际执行的 FFmpeg 命令</p>
                        <div id="cmd-output" class="text-sky-300 break-all"></div>
                        <div id="error-output" class="mt-4 text-rose-400 whitespace-pre-wrap"></div>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <!-- Custom Modal -->
    <div id="preset-modal"
        class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="glass w-full max-w-md rounded-2xl p-6 shadow-2xl scale-95 transition-transform duration-300"
            id="modal-content">
            <h3 class="text-xl font-bold mb-2 text-sky-400">🔥 保存当前预设</h3>
            <p class="text-slate-400 text-sm mb-6">为这一刻的调色灵感起一个独特的名字。</p>
            <input type="text" id="preset-name-input" placeholder="例如：电影感、复古冷调..."
                class="w-full bg-slate-800 border-none rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-sky-500 transition-all mb-8">
            <div class="flex gap-3 justify-end">
                <button onclick="closePresetModal()"
                    class="px-6 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 transition-colors text-sm">算了</button>
                <button onclick="confirmSavePreset()"
                    class="px-8 py-2 rounded-lg bg-sky-600 hover:bg-sky-500 transition-colors text-sm font-bold shadow-lg shadow-sky-500/20">确认保存</button>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast"
        class="fixed bottom-8 right-8 translate-y-20 opacity-0 transition-all duration-300 pointer-events-none">
        <div class="bg-indigo-600 text-white px-6 py-3 rounded-xl shadow-2xl flex items-center gap-3">
            <i data-lucide="check-circle" class="w-5 h-5"></i>
            <span id="toast-msg">提示信息</span>
        </div>
    </div>

    <script>
        // Initialize Lucide Icons
        lucide.createIcons();

        // Elements
        const fileInput = document.getElementById('file-input');
        const dropArea = document.getElementById('drop-area');
        const generateBtn = document.getElementById('generate-btn');
        const loadingOverlay = document.getElementById('loading-overlay');
        const cmdOutput = document.getElementById('cmd-output');
        const errorOutput = document.getElementById('error-output');
        const execTime = document.getElementById('exec-time');

        let currentFile = null;

        // Sync Range and Number Inputs
        function bindInput(id, def) {
            const range = document.getElementById('range-' + id);
            const val = document.getElementById('val-' + id);
            if (!range || !val) return;

            val.value = def;
            range.value = def;

            range.addEventListener('input', () => val.value = range.value);
            val.addEventListener('input', () => range.value = val.value);
        }

        bindInput('brightness', 0);
        bindInput('contrast', 1);
        bindInput('saturation', 1);
        bindInput('temp', 6500);

        // Special displays for sharpen/blur
        document.getElementById('range-unsharp').addEventListener('input', (e) => {
            document.getElementById('val-unsharp-display').textContent = e.target.value;
        });
        document.getElementById('range-blur').addEventListener('input', (e) => {
            document.getElementById('val-blur-display').textContent = e.target.value;
        });

        // Upload Handling
        dropArea.onclick = () => fileInput.click();
        fileInput.onchange = (e) => handleFiles(e.target.files);

        dropArea.ondragover = (e) => { e.preventDefault(); dropArea.classList.add('border-sky-500'); };
        dropArea.ondragleave = () => dropArea.classList.remove('border-sky-500');
        dropArea.ondrop = (e) => {
            e.preventDefault();
            dropArea.classList.remove('border-sky-500');
            handleFiles(e.dataTransfer.files);
        };

        function handleFiles(files) {
            if (files.length === 0) return;
            const file = files[0];
            if (file.size > 50 * 1024 * 1024) {
                showToast('文件不能超过 50MB', 'error');
                return;
            }

            currentFile = file;
            document.getElementById('filename').textContent = file.name;
            document.getElementById('file-info').classList.remove('hidden');

            const url = URL.createObjectURL(file);
            const isVideo = file.type.startsWith('video/');

            // Show original
            document.getElementById('original-placeholder').classList.add('hidden');
            if (isVideo) {
                document.getElementById('original-video').src = url;
                document.getElementById('original-video').classList.remove('hidden');
                document.getElementById('original-image').classList.add('hidden');
            } else {
                document.getElementById('original-image').src = url;
                document.getElementById('original-image').classList.remove('hidden');
                document.getElementById('original-video').classList.add('hidden');
            }
        }

        // Generate Preview
        generateBtn.onclick = async () => {
            if (!currentFile) {
                showToast('请先上传文件');
                return;
            }

            loadingOverlay.classList.remove('hidden');
            loadingOverlay.classList.add('flex');
            errorOutput.textContent = '';

            const formData = new FormData();
            formData.append('file', currentFile);
            formData.append('brightness', document.getElementById('val-brightness').value);
            formData.append('contrast', document.getElementById('val-contrast').value);
            formData.append('saturation', document.getElementById('val-saturation').value);
            formData.append('temp', document.getElementById('val-temp').value);
            formData.append('unsharp', document.getElementById('range-unsharp').value);
            formData.append('blur', document.getElementById('range-blur').value);

            try {
                const resp = await fetch('process.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await resp.json();

                if (result.success) {
                    cmdOutput.textContent = result.cmd;
                    execTime.textContent = `Time: ${result.duration.toFixed(2)}s`;

                    document.getElementById('processed-placeholder').classList.add('hidden');
                    const isVideo = currentFile.type.startsWith('video/');

                    if (isVideo) {
                        const video = document.getElementById('processed-video');
                        video.src = result.url + '?t=' + Date.now();
                        video.classList.remove('hidden');
                        document.getElementById('processed-image').classList.add('hidden');
                        video.load();
                    } else {
                        const img = document.getElementById('processed-image');
                        img.src = result.url + '?t=' + Date.now();
                        img.classList.remove('hidden');
                        document.getElementById('processed-video').classList.add('hidden');
                    }

                    // Setup Download Button
                    const downloadBtn = document.getElementById('download-btn');
                    downloadBtn.href = result.url;
                    downloadBtn.download = `processed_${Date.now()}.${isVideo ? 'mp4' : 'jpg'}`;
                    downloadBtn.classList.remove('hidden');

                    showToast('渲染完成');
                } else {
                    errorOutput.textContent = result.error || '执行失败';
                    showToast('处理出错', 'error');
                }
            } catch (err) {
                errorOutput.textContent = err.message;
                showToast('请求失败', 'error');
            } finally {
                loadingOverlay.classList.add('hidden');
                loadingOverlay.classList.remove('flex');
            }
        };

        // Presets Logic
        function loadPreset(params) {
            document.getElementById('val-brightness').value = params.brightness;
            document.getElementById('range-brightness').value = params.brightness;
            document.getElementById('val-contrast').value = params.contrast;
            document.getElementById('range-contrast').value = params.contrast;
            document.getElementById('val-saturation').value = params.saturation;
            document.getElementById('range-saturation').value = params.saturation;
            document.getElementById('val-temp').value = params.temp;
            document.getElementById('range-temp').value = params.temp;
            document.getElementById('range-unsharp').value = params.unsharp.split(':')[0] || 0;
            document.getElementById('val-unsharp-display').textContent = params.unsharp.split(':')[0] || 0;
            document.getElementById('range-blur').value = params.blur || 0;
            document.getElementById('val-blur-display').textContent = params.blur || 0;
            document.getElementById('custom-vf').value = params.custom || '';
            showToast('已加载预设');
        }

        function savePreset() {
            document.getElementById('preset-modal').classList.remove('hidden');
            document.getElementById('preset-modal').classList.add('flex');
            setTimeout(() => document.getElementById('modal-content').classList.remove('scale-95'), 10);
            document.getElementById('preset-name-input').focus();
        }

        function closePresetModal() {
            document.getElementById('modal-content').classList.add('scale-95');
            setTimeout(() => {
                document.getElementById('preset-modal').classList.add('hidden');
                document.getElementById('preset-modal').classList.remove('flex');
                document.getElementById('preset-name-input').value = '';
            }, 200);
        }

        async function confirmSavePreset() {
            const name = document.getElementById('preset-name-input').value;
            if (!name) {
                showToast('名称不能为空', 'error');
                return;
            }

            const params = {
                brightness: document.getElementById('val-brightness').value,
                contrast: document.getElementById('val-contrast').value,
                saturation: document.getElementById('val-saturation').value,
                temp: document.getElementById('val-temp').value,
                unsharp: document.getElementById('range-unsharp').value + ':5:1.0',
                blur: document.getElementById('range-blur').value,
                custom: document.getElementById('custom-vf').value
            };

            const resp = await fetch('process.php?action=save_preset', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name, params })
            });

            if (resp.ok) {
                closePresetModal();
                showToast('预设已保存');
                setTimeout(() => location.reload(), 500);
            } else {
                showToast('保存失败', 'error');
            }
        }

        function resetParams() {
            loadPreset({
                brightness: 0,
                contrast: 1,
                saturation: 1,
                temp: 6500,
                unsharp: "0:5:1.0",
                blur: 0,
                custom: ""
            });
        }

        function showToast(msg, type = 'success') {
            const toast = document.getElementById('toast');
            const toastMsg = document.getElementById('toast-msg');
            toastMsg.textContent = msg;
            toast.classList.remove('translate-y-20', 'opacity-0');
            toast.classList.add('translate-y-0', 'opacity-100');
            setTimeout(() => {
                toast.classList.add('translate-y-20', 'opacity-0');
                toast.classList.remove('translate-y-0', 'opacity-100');
            }, 3000);
        }
    </script>
</body>

</html>