# FFmpeg Filter Lab - 滤镜参数高度调试工具

这是一个基于 PHP + FFmpeg 打造的本地 Web 预览工具，致力于帮助视频创作者和开发者通过可视化界面快速找出最适合的 FFmpeg 滤镜参数组合。

## ✨ 功能特性

- **多媒体支持**: 支持上传 MP4/MOV 视频或 JPG/PNG/WebP 图片。
- **直观调参**: 亮度、对比度、饱和度、色温、锐化、模糊等常用参数通过滑块与数字精确控制。
- **即时预览**: 实时生成处理后的预览效果（视频自动截取前 5s 以保证渲染效率）。
- **预设系统**: 灵感闪现时一键保存当前参数为预设，支持快速切换对比。
- **命令透出**: 实时展示背后执行的 FFmpeg 完整指令，方便开发者直接复制使用。
- **跨平台兼容**: 采用 Docker 命名卷（Named Volumes）隔离缓冲文件，原生完美兼容 Windows / macOS / Linux。
- **精致 UI**: 工业风深色模式，毛玻璃质感，提供沉浸式调试体验。

## 🛠 技术栈

- **Frontend**: Vanilla JS + Tailwind CSS (Play CDN) + Lucide Icons
- **Backend**: Native PHP 8.2 (No Frameworks)
- **Engine**: FFmpeg
- **Container**: Docker + Nginx + PHP-FPM

## 🚀 启动指南

1. **环境准备**: 确保已安装 Docker 和 Docker Desktop。
2. **启动项目**: 在根目录执行以下命令：
   ```bash
   docker compose up --build
   ```
3. **访问地址**: 打开浏览器访问 [http://localhost:8080](http://localhost:8080)。
4. **开始调试**:
   - 上传一张图片或一段视频。
   - 拖动左侧滑块调整参数。
   - 点击 **“生成预览”** 查看右侧结果。
   - 在控制台查看生成的 FFmpeg 命令。

## 📂 项目结构

- `public/index.php`: 主界面与前端逻辑。
- `public/process.php`: 后端 FFmpeg 调用逻辑与预设保存。
- `presets.json`: 存储用户定义的预设。
- `Dockerfile` & `docker-compose.yml`: 环境配置。

## ⚠️ 注意事项

- **文件大小**: 单次上传限制为 50MB。
- **处理时长**: 单次处理超时时间设为 15 秒。
- **预览长度**: 为保证调试效率，视频文件仅截取前 5 秒进行滤镜处理。
- **安全性**: 命令执行经过 `escapeshellarg` 过滤，但建议仅在本地或受信任环境运行。

---
*Powered by Antigravity*
