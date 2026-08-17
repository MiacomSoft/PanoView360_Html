/**
 * Upload Manager - Mass file upload with preview
 */
class UploadManager {
    constructor(options = {}) {
        // Определяем базовый путь к проекту
        const pathname = window.location.pathname;
        // Убираем имя файла, оставляем только путь к папке
        let basePath = pathname.substring(0, pathname.lastIndexOf('/'));
        // Если путь пустой (корень) - оставляем пустую строку
        if (basePath === '') {
            basePath = '';
        }
        const rootPath = window.location.origin + basePath;

        this.options = {
            uploadUrl: rootPath + '/php/upload_handler.php',
            listUrl: rootPath + '/php/file_list.php',
            initUrl: rootPath + '/php/init.php',
            projectsUrl: rootPath + '/php/projects_list.php',
            container: '#uploadContainer',
            maxFiles: 50,
            maxFileSize: 50 * 1024 * 1024,
            allowedTypes: ['image/jpeg', 'image/png', 'image/jpg'],
            projectName: '',
            basePath: '',
            rootPath: rootPath,
            imageRoot: rootPath + '/img/',
            ...options
        };

        this.files = [];
        this.selectedFile = null;
        this.uploading = false;
        this.uploadedFiles = [];
        this.filteredFiles = [];
        this.initialized = false;
        this.searchTerm = '';

        console.log('UploadManager initialized with rootPath:', this.options.rootPath);
        console.log('Upload URL:', this.options.uploadUrl);
        console.log('List URL:', this.options.listUrl);
        console.log('Image root:', this.options.imageRoot);

        this.init();
    }

    init() {
        this.renderUI();
        this.bindEvents();

        // Получаем имя проекта из URL
        this.detectProjectName();

        setTimeout(() => {
            this.checkInitialization();
        }, 300);
    }

    detectProjectName() {
        // Получаем project_name из URL параметров
        const params = new URLSearchParams(window.location.search);
        const projectName = params.get('project_name') || 'default';
        this.options.projectName = projectName;

        // Определяем basePath
        const pathParam = params.get('path') || this.options.rootPath;
        this.options.basePath = pathParam + '/img/' + projectName;

        console.log('Project detected:', {
            projectName: this.options.projectName,
            basePath: this.options.basePath,
            rootPath: this.options.rootPath
        });

        // Обновляем заголовок
        const fileCount = document.getElementById('fileCount');
        if (fileCount) {
            fileCount.textContent = `Проект: ${this.options.projectName} (0 файлов)`;
        }
    }

    /**
     * Устанавливает текущий проект
     * @param {string} projectName - Имя проекта
     */
    setProject(projectName) {
        this.options.projectName = projectName;

        // Обновляем информацию о проекте в UI
        const projectInfo = document.getElementById('projectInfo');
        if (projectInfo) {
            projectInfo.textContent = projectName ? `Проект: ${projectName}` : 'Проект: не выбран';
        }

        const fileCount = document.getElementById('fileCount');
        if (fileCount) {
            fileCount.textContent = projectName ?
                `Проект: ${projectName} (загрузка...)` :
                'Проект не выбран';
        }

        console.log('Project set to:', projectName);

        // Если проект выбран - загружаем список файлов
        if (projectName) {
            // Проверяем и создаем .project.json если необходимо
            this.ensureProjectConfig().then(() => {
                this.loadFileList();
            });
        } else {
            // Очищаем список
            const fileList = document.getElementById('fileList');
            if (fileList) {
                fileList.innerHTML = '<div class="empty-message">📭 Выберите проект для загрузки файлов</div>';
            }
        }
    }

    /**
     * Проверяет и создает .project.json если необходимо
     */
    async ensureProjectConfig() {
        if (!this.options.projectName) return;

        try {
            // Проверяем существование конфига через API
            const url = `${this.options.projectsUrl}?project=${encodeURIComponent(this.options.projectName)}`;
            const response = await fetch(url);
            const data = await response.json();

            if (data.success && data.config) {
                console.log('Project config loaded:', data.config);
                return data.config;
            } else {
                // Если конфиг не загрузился - пробуем создать
                console.log('Project config not found, creating...');
                const createResponse = await fetch(this.options.projectsUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'create',
                        project: this.options.projectName
                    })
                });
                const createData = await createResponse.json();
                if (createData.success) {
                    console.log('Project config created successfully');
                    return createData.config;
                }
            }
        } catch (error) {
            console.error('Error ensuring project config:', error);
        }
        return null;
    }

    renderUI() {
        const container = document.querySelector(this.options.container);
        if (!container) {
            console.error('Container not found:', this.options.container);
            return;
        }

        container.innerHTML = `
            <div class="upload-manager">
                <div class="upload-area" id="dropArea">
                    <div class="upload-icon">📁</div>
                    <div class="upload-text">
                        <strong>Перетащите файлы сюда</strong>
                        <span>или кликните для выбора</span>
                    </div>
                    <input type="file" id="fileInput" multiple accept="image/*" style="display:none;">
                    <div class="upload-info">
                        <span>Макс. размер: 50MB</span>
                        <span>Форматы: JPG, PNG</span>
                        <span id="projectInfo">Проект: ${this.options.projectName || '...'}</span>
                    </div>
                </div>

                <div class="upload-progress" id="uploadProgress" style="display:none;">
                    <div class="progress-bar-container">
                        <div class="progress-bar" id="progressBar" style="width:0%;"></div>
                    </div>
                    <div class="progress-text" id="progressText">Загрузка...</div>
                </div>

                <div class="file-list-container" id="fileListContainer">
                    <div class="file-list-header">
                        <span class="file-count" id="fileCount">Загруженные файлы (0)</span>
                        <div class="file-list-actions">
                            <input type="text" id="fileSearchInput" placeholder="🔍 Поиск файлов..." class="file-search-input">
                            <button class="btn-refresh" id="refreshBtn">🔄 Обновить</button>
                        </div>
                    </div>
                    <div class="file-list" id="fileList">
                        <div class="empty-message">Загрузка списка...</div>
                    </div>
                </div>
            </div>
        `;

        // Обновляем информацию о проекте
        const projectInfo = document.getElementById('projectInfo');
        if (projectInfo && this.options.projectName) {
            projectInfo.textContent = `Проект: ${this.options.projectName}`;
        }

        this.injectStyles();
    }

    injectStyles() {
        if (document.getElementById('uploadManagerStyles')) return;

        const styles = `
        <style id="uploadManagerStyles">
            .upload-manager {
                font-family: Arial, sans-serif;
                max-width: 100%;
                padding: 10px;
            }

            .upload-area {
                border: 2px dashed #dee2e6;
                border-radius: 10px;
                padding: 30px 20px;
                text-align: center;
                cursor: pointer;
                transition: all 0.3s ease;
                background: #f8f9fa;
                position: relative;
            }

            .upload-area:hover {
                border-color: #4CAF50;
                background: #f0f8f0;
            }

            .upload-area.dragover {
                border-color: #4CAF50;
                background: #e8f5e9;
                transform: scale(1.02);
            }

            .upload-area.has-files {
                padding: 15px 20px;
                border-color: #4CAF50;
                background: #e8f5e9;
            }

            .upload-icon {
                font-size: 48px;
                margin-bottom: 10px;
            }

            .upload-text {
                color: #555;
            }

            .upload-text strong {
                display: block;
                font-size: 18px;
                color: #333;
            }

            .upload-text span {
                font-size: 14px;
                color: #888;
            }

            .upload-info {
                margin-top: 10px;
                font-size: 12px;
                color: #999;
                display: flex;
                gap: 15px;
                justify-content: center;
                flex-wrap: wrap;
            }

            .upload-info #projectInfo {
                font-weight: bold;
                color: #4CAF50;
            }

            .upload-progress {
                margin: 15px 0;
                padding: 15px;
                background: #f8f9fa;
                border-radius: 8px;
            }

            .progress-bar-container {
                height: 8px;
                background: #e9ecef;
                border-radius: 4px;
                overflow: hidden;
            }

            .progress-bar {
                height: 100%;
                background: linear-gradient(90deg, #4CAF50, #8BC34A);
                transition: width 0.3s ease;
                width: 0%;
            }

            .progress-text {
                font-size: 13px;
                color: #555;
                margin-top: 8px;
                text-align: center;
            }

            .file-list-container {
                margin-top: 20px;
                border: 1px solid #dee2e6;
                border-radius: 8px;
                overflow: hidden;
            }

            .file-list-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 12px 16px;
                background: #f8f9fa;
                border-bottom: 1px solid #dee2e6;
                flex-wrap: wrap;
                gap: 8px;
            }

            .file-list-actions {
                display: flex;
                gap: 8px;
                align-items: center;
                flex-wrap: wrap;
            }

            .file-search-input {
                padding: 6px 12px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 13px;
                min-width: 180px;
                outline: none;
                transition: border-color 0.2s;
            }

            .file-search-input:focus {
                border-color: #4CAF50;
                box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.2);
            }

            .file-search-input::placeholder {
                color: #aaa;
            }

            .file-count {
                font-weight: 600;
                color: #333;
                font-size: 14px;
                white-space: nowrap;
            }

            .btn-refresh {
                padding: 4px 12px;
                border: 1px solid #dee2e6;
                border-radius: 4px;
                background: white;
                cursor: pointer;
                font-size: 13px;
                transition: all 0.2s;
                white-space: nowrap;
            }

            .btn-refresh:hover {
                background: #f0f0f0;
                border-color: #bbb;
            }

            .file-list {
                max-height: 300px;
                overflow-y: auto;
            }

            .file-list .empty-message {
                padding: 30px;
                text-align: center;
                color: #999;
                font-style: italic;
            }

            .file-item {
                display: flex;
                align-items: center;
                padding: 10px 16px;
                border-bottom: 1px solid #f0f0f0;
                cursor: pointer;
                transition: background 0.2s;
            }

            .file-item:hover {
                background: #f5f5f5;
            }

            .file-item.selected {
                background: #e3f2fd;
                border-left: 3px solid #2196F3;
            }

            .file-item .file-icon {
                width: 40px;
                height: 40px;
                border-radius: 4px;
                margin-right: 12px;
                background: #f0f0f0;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 24px;
                flex-shrink: 0;
                color: #999;
            }

            .file-item .file-info {
                flex: 1;
                min-width: 0;
            }

            .file-item .file-name {
                font-weight: 500;
                color: #333;
                font-size: 14px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .file-item .file-size {
                font-size: 12px;
                color: #999;
            }

            .file-item .file-actions {
                display: flex;
                gap: 5px;
                flex-shrink: 0;
            }

            .file-item .btn-delete-upload {
                padding: 4px 8px;
                border: none;
                border-radius: 4px;
                background: #dc3545;
                color: white;
                cursor: pointer;
                font-size: 12px;
                opacity: 0.7;
                transition: opacity 0.2s;
            }

            .file-item .btn-delete-upload:hover {
                opacity: 1;
            }

            .file-item .btn-select-file {
                padding: 4px 12px;
                border: none;
                border-radius: 4px;
                background: #4CAF50;
                color: white;
                cursor: pointer;
                font-size: 12px;
            }

            .file-item .btn-select-file:hover {
                background: #45a049;
            }

            .file-item .btn-select-file.selected {
                background: #ff9800;
            }

            .file-item .file-name .highlight {
                background: #ffeb3b;
                padding: 0 2px;
                border-radius: 2px;
            }

            .upload-actions {
                display: flex;
                gap: 10px;
                margin-top: 15px;
                flex-wrap: wrap;
            }

            .upload-actions .btn {
                padding: 8px 20px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
                transition: all 0.2s;
            }

            .upload-actions .btn-primary {
                background: #4CAF50;
                color: white;
            }

            .upload-actions .btn-primary:hover {
                background: #45a049;
            }

            .upload-actions .btn-primary:disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }

            .upload-actions .btn-secondary {
                background: #6c757d;
                color: white;
            }

            .upload-actions .btn-secondary:hover {
                background: #5a6268;
            }

            .upload-actions .btn-danger {
                background: #dc3545;
                color: white;
            }

            .upload-actions .btn-danger:hover {
                background: #c82333;
            }

            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }
        </style>
        `;

        document.head.insertAdjacentHTML('beforeend', styles);
    }

    bindEvents() {
        const dropArea = document.getElementById('dropArea');
        const fileInput = document.getElementById('fileInput');
        const refreshBtn = document.getElementById('refreshBtn');
        const searchInput = document.getElementById('fileSearchInput');

        if (!dropArea || !fileInput || !refreshBtn) {
            console.error('Required elements not found');
            return;
        }

        dropArea.addEventListener('click', () => fileInput.click());

        dropArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropArea.classList.add('dragover');
        });

        dropArea.addEventListener('dragleave', () => {
            dropArea.classList.remove('dragover');
        });

        dropArea.addEventListener('drop', (e) => {
            e.preventDefault();
            dropArea.classList.remove('dragover');
            this.handleFiles(e.dataTransfer.files);
        });

        fileInput.addEventListener('change', () => {
            if (fileInput.files.length > 0) {
                this.handleFiles(fileInput.files);
                fileInput.value = '';
            }
        });

        refreshBtn.addEventListener('click', () => this.loadFileList());

        // Поиск с задержкой
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.searchTerm = e.target.value.trim().toLowerCase();
                    this.applyFilter();
                }, 300);
            });

            // Очистка поиска по Escape
            searchInput.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    searchInput.value = '';
                    this.searchTerm = '';
                    this.applyFilter();
                    searchInput.blur();
                }
            });
        }
    }

    handleFiles(files) {
        if (this.uploading) {
            showToast('⚠️ Загрузка уже выполняется', 3000);
            return;
        }

        if (!this.options.projectName) {
            showToast('❌ Имя проекта не определено', 3000);
            return;
        }

        const validFiles = [];
        const errors = [];

        for (let i = 0; i < files.length; i++) {
            const file = files[i];

            const fileType = file.type || this.getMimeType(file.name);
            if (!this.options.allowedTypes.includes(fileType)) {
                errors.push(`${file.name}: Неподдерживаемый формат`);
                continue;
            }

            if (file.size > this.options.maxFileSize) {
                errors.push(`${file.name}: Слишком большой файл (макс. ${this.options.maxFileSize / 1024 / 1024}MB)`);
                continue;
            }

            validFiles.push(file);
        }

        if (errors.length > 0) {
            showToast('⚠️ ' + errors.join('\n'), 5000);
        }

        if (validFiles.length > 0) {
            this.uploadFiles(validFiles);
        }
    }

    getMimeType(filename) {
        const ext = filename.split('.').pop().toLowerCase();
        const mimeMap = {
            'jpg': 'image/jpeg',
            'jpeg': 'image/jpeg',
            'png': 'image/png'
        };
        return mimeMap[ext] || 'application/octet-stream';
    }

    async uploadFiles(files) {
        this.uploading = true;
        const progressBar = document.getElementById('progressBar');
        const progressText = document.getElementById('progressText');
        const progressContainer = document.getElementById('uploadProgress');

        if (!progressBar || !progressText || !progressContainer) {
            this.uploading = false;
            return;
        }

        progressContainer.style.display = 'block';
        progressBar.style.width = '0%';
        progressText.textContent = 'Подготовка к загрузке...';

        const formData = new FormData();
        formData.append('project', this.options.projectName);
        files.forEach(file => formData.append('files[]', file));

        try {
            const xhr = new XMLHttpRequest();

            xhr.upload.onprogress = (e) => {
                if (e.lengthComputable) {
                    const percent = Math.round((e.loaded / e.total) * 100);
                    progressBar.style.width = percent + '%';
                    progressText.textContent = `Загрузка: ${percent}% (${this.formatSize(e.loaded)} из ${this.formatSize(e.total)})`;
                }
            };

            xhr.onload = () => {
                progressContainer.style.display = 'none';
                this.uploading = false;

                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            const count = response.total || response.uploaded?.length || 0;
                            showToast(`✅ Загружено файлов: ${count} в проект ${response.project}`, 3000);
                            this.loadFileList();
                        } else {
                            showToast('❌ Ошибка загрузки: ' + (response.error || 'Неизвестная ошибка'), 5000);
                        }
                    } catch (e) {
                        showToast('❌ Ошибка обработки ответа сервера', 5000);
                        console.error('Parse error:', e, xhr.responseText);
                    }
                } else {
                    showToast('❌ Ошибка загрузки (код: ' + xhr.status + ')', 5000);
                }
            };

            xhr.onerror = () => {
                progressContainer.style.display = 'none';
                this.uploading = false;
                showToast('❌ Ошибка сети при загрузке файлов', 5000);
            };

            xhr.open('POST', this.options.uploadUrl, true);
            xhr.send(formData);

        } catch (error) {
            progressContainer.style.display = 'none';
            this.uploading = false;
            showToast('❌ Ошибка: ' + error.message, 5000);
        }
    }

    async checkInitialization() {
        const fileList = document.getElementById('fileList');
        if (!fileList) return;

        if (!this.options.projectName) {
            fileList.innerHTML = `
                <div class="empty-message" style="color:#ff9800;">
                    ⚠️ Имя проекта не определено
                    <br><br>
                    <small style="color:#999;">Добавьте параметр project_name в URL</small>
                </div>
            `;
            return;
        }

        try {
            const url = `${this.options.listUrl}?project=${encodeURIComponent(this.options.projectName)}`;
            console.log('Checking initialization at:', url);
            const response = await fetch(url);

            if (response.ok) {
                this.initialized = true;
                this.loadFileList();
            } else {
                throw new Error(`HTTP ${response.status}`);
            }
        } catch (error) {
            console.error('Initialization check failed:', error);
            fileList.innerHTML = `
                <div class="empty-message" style="color:#dc3545;">
                    ❌ Ошибка подключения к серверу
                    <br><br>
                    <button onclick="window.uploadManager.loadFileList()" 
                            style="padding:8px 20px;border:none;border-radius:4px;
                                   background:#4CAF50;color:white;cursor:pointer;font-size:14px;">
                        🔄 Попробовать снова
                    </button>
                    <br><br>
                    <small style="color:#999;">Путь: ${this.options.listUrl}</small>
                </div>
            `;
        }
    }

    async loadFileList() {
        const fileList = document.getElementById('fileList');
        const fileCount = document.getElementById('fileCount');

        if (!fileList) return;

        if (!this.options.projectName) {
            fileList.innerHTML = '<div class="empty-message">⚠️ Имя проекта не определено</div>';
            return;
        }

        fileList.innerHTML = '<div class="empty-message">⏳ Загрузка списка...</div>';

        // Очищаем поиск
        const searchInput = document.getElementById('fileSearchInput');
        if (searchInput) {
            searchInput.value = '';
        }
        this.searchTerm = '';

        try {
            const url = `${this.options.listUrl}?project=${encodeURIComponent(this.options.projectName)}`;
            console.log('Loading file list from:', url);
            const response = await fetch(url);

            if (!response.ok) {
                const errorText = await response.text();
                console.error('Error response:', errorText);
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.error || 'Ошибка загрузки списка');
            }

            this.uploadedFiles = data.files || [];
            this.filteredFiles = [...this.uploadedFiles];
            this.renderFileList(this.filteredFiles);

            if (fileCount) {
                fileCount.textContent = `Проект: ${this.options.projectName} (${this.uploadedFiles.length} файлов)`;
            }

        } catch (error) {
            console.error('Error loading file list:', error);
            fileList.innerHTML = `
                <div class="empty-message" style="color:#dc3545;">
                    ❌ Ошибка загрузки списка: ${error.message}
                    <br><br>
                    <button onclick="window.uploadManager.loadFileList()" 
                            style="padding:4px 12px;border:1px solid #dc3545;border-radius:4px;
                                   background:white;cursor:pointer;font-size:13px;">
                        🔄 Попробовать снова
                    </button>
                    <br><br>
                    <small style="color:#999;">Путь: ${this.options.listUrl}</small>
                </div>
            `;
        }
    }

    /**
     * Применяет фильтр к списку файлов
     */
    applyFilter() {
        if (!this.searchTerm) {
            this.filteredFiles = [...this.uploadedFiles];
        } else {
            this.filteredFiles = this.uploadedFiles.filter(file => {
                return file.name.toLowerCase().includes(this.searchTerm);
            });
        }
        this.renderFileList(this.filteredFiles);

        // Обновляем счетчик
        const fileCount = document.getElementById('fileCount');
        if (fileCount) {
            const total = this.uploadedFiles.length;
            const filtered = this.filteredFiles.length;
            if (this.searchTerm) {
                fileCount.textContent = `Найдено: ${filtered} из ${total} файлов`;
            } else {
                fileCount.textContent = `Проект: ${this.options.projectName} (${total} файлов)`;
            }
        }
    }

    /**
     * Отображает список файлов БЕЗ загрузки превью
     * Превью загружается только при выборе файла
     */
    renderFileList(files) {
        const fileList = document.getElementById('fileList');

        if (!fileList) return;

        if (!files || files.length === 0) {
            const message = this.searchTerm
                ? `<div class="empty-message">🔍 Ничего не найдено по запросу "${this.searchTerm}"</div>`
                : '<div class="empty-message">📭 Нет загруженных файлов</div>';
            fileList.innerHTML = message;
            return;
        }

        fileList.innerHTML = '';

        files.forEach((file) => {
            const item = document.createElement('div');
            item.className = 'file-item';
            item.dataset.filename = file.name;

            // Если это выбранный файл
            if (this.selectedFile && this.selectedFile.name === file.name) {
                item.classList.add('selected');
            }

            // Иконка вместо превью
            const icon = document.createElement('div');
            icon.className = 'file-icon';
            const ext = file.name.split('.').pop().toLowerCase();
            if (['jpg', 'jpeg', 'png'].includes(ext)) {
                icon.textContent = '🖼️';
            } else {
                icon.textContent = '📄';
            }

            const info = document.createElement('div');
            info.className = 'file-info';

            // Подсветка совпадений в имени файла
            let displayName = this.escapeHtml(file.name);
            if (this.searchTerm) {
                const regex = new RegExp(`(${this.escapeRegex(this.searchTerm)})`, 'gi');
                displayName = displayName.replace(regex, '<span class="highlight">$1</span>');
            }

            info.innerHTML = `
                <div class="file-name" title="${this.escapeHtml(file.name)}">${displayName}</div>
                <div class="file-size">${this.formatSize(file.size)}</div>
            `;

            const actions = document.createElement('div');
            actions.className = 'file-actions';

            const selectBtn = document.createElement('button');
            selectBtn.className = 'btn-select-file';
            selectBtn.textContent = 'Выбрать';
            selectBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.selectFile(file);
            });

            const deleteBtn = document.createElement('button');
            deleteBtn.className = 'btn-delete-upload';
            deleteBtn.textContent = '✕';
            deleteBtn.title = 'Удалить файл';
            deleteBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.deleteFile(file.name);
            });

            actions.appendChild(selectBtn);
            actions.appendChild(deleteBtn);

            item.appendChild(icon);
            item.appendChild(info);
            item.appendChild(actions);

            item.addEventListener('click', () => {
                this.selectFile(file);
            });

            fileList.appendChild(item);
        });
    }

    /**
     * Экранирует специальные символы для RegExp
     */
    escapeRegex(str) {
        return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    /**
     * Выбор файла - здесь загружается превью и заполняются поля
     */
    selectFile(file) {
        this.selectedFile = file;

        // Обновляем выделение в списке
        document.querySelectorAll('.file-item').forEach(el => {
            el.classList.remove('selected');
        });

        const selectedEl = document.querySelector(`.file-item[data-filename="${file.name}"]`);
        if (selectedEl) {
            selectedEl.classList.add('selected');
        }

        // Получаем параметры из URL
        const params = new URLSearchParams(window.location.search);
        const projectName = params.get('project_name') || 'default';
        const basePath = this.options.rootPath;

        // Формируем правильные пути с учетом корневого пути
        const correctPath = basePath + '/img';
        const correctPhoto = '/img/' + projectName + '/' + file.name;
        const fullPath = correctPath + correctPhoto;

        const eventData = {
            file: file,
            name: file.name,
            url: this.options.rootPath + file.url,
            relativePath: correctPhoto,
            fullPath: fullPath,
            projectName: projectName,
            basePath: correctPath,
            path: correctPath,
            photo: correctPhoto
        };

        console.log('File selected, event data:', eventData);

        document.dispatchEvent(new CustomEvent('fileSelected', {
            detail: eventData
        }));

        showToast('✅ Выбран файл: ' + file.name, 2000);
    }

    async deleteFile(filename) {
        if (!confirm(`Удалить файл "${filename}"?`)) return;

        try {
            const response = await fetch(this.options.uploadUrl, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    project: this.options.projectName,
                    filename: filename
                })
            });

            const data = await response.json();

            if (data.success) {
                showToast('🗑️ Файл удален: ' + filename, 3000);
                this.loadFileList();

                if (this.selectedFile && this.selectedFile.name === filename) {
                    this.selectedFile = null;
                }
            } else {
                showToast('❌ Ошибка удаления: ' + (data.error || 'Неизвестная ошибка'), 5000);
            }
        } catch (error) {
            showToast('❌ Ошибка: ' + error.message, 5000);
        }
    }

    formatSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        if (bytes < 1024 * 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        return (bytes / (1024 * 1024 * 1024)).toFixed(2) + ' GB';
    }

    escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    getSelectedFile() {
        return this.selectedFile;
    }

    getUploadedFiles() {
        return this.uploadedFiles;
    }
}

// Toast функция
function showToast(message, duration) {
    duration = duration || 3000;

    const oldToasts = document.querySelectorAll('.custom-toast');
    oldToasts.forEach(toast => toast.remove());

    var toast = document.createElement('div');
    toast.className = 'custom-toast';
    toast.style.cssText = [
        'position:fixed',
        'bottom:80px',
        'right:20px',
        'background:rgba(0,0,0,0.85)',
        'color:white',
        'padding:12px 24px',
        'border-radius:8px',
        'z-index:99999',
        'font-family:Arial,sans-serif',
        'font-size:14px',
        'max-width:450px',
        'min-width:200px',
        'box-shadow:0 4px 12px rgba(0,0,0,0.3)',
        'animation:fadeIn 0.3s ease',
        'white-space:pre-line',
        'word-break:break-word'
    ].join(';');

    toast.innerHTML = message.replace(/\n/g, '<br>');
    document.body.appendChild(toast);

    setTimeout(function() {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.5s ease';
        setTimeout(function() {
            if (toast.parentNode) {
                toast.remove();
            }
        }, 500);
    }, duration);
}

// Инициализация
document.addEventListener('DOMContentLoaded', function() {
    const container = document.querySelector('#uploadContainer');
    if (container && !window.uploadManager) {
        console.log('Initializing Upload Manager...');
        try {
            window.uploadManager = new UploadManager({
                container: '#uploadContainer'
            });
            console.log('Upload Manager initialized successfully');
        } catch (error) {
            console.error('Failed to initialize Upload Manager:', error);
        }
    }
});