# PanoView360_Html - Интерактивный редактор и плеер панорамных туров

<p align="center">
  <img src="https://img.shields.io/badge/version-2.5.6-blue.svg" alt="Version">
  <img src="https://img.shields.io/badge/license-MIT-green.svg" alt="License">
  <img src="https://img.shields.io/badge/WebGL-supported-brightgreen.svg" alt="WebGL">
  <img src="https://img.shields.io/badge/JavaScript-ES6-yellow.svg" alt="JavaScript">
</p>

Пример:
<br/>[Панорама просмотра 1](https://raw.githack.com/MiacomSoft/PanoView360_Html/main/viewer_preview.html?photo=https://raw.githubusercontent.com/MiacomSoft/PanoView360_Html/main/img/04.01.2026/DSCN0021.JPG)
<br/>[Панорама просмотра 2](https://raw.githack.com/MiacomSoft/PanoView360_Html/main/viewer_preview.html?photo=https://raw.githubusercontent.com/MiacomSoft/PanoView360_Html/main/img/04.01.2026/DSCN0054.JPG)
<br/>[Панорама просмотра 3](https://raw.githack.com/MiacomSoft/PanoView360_Html/main/viewer_preview.html?photo=https://raw.githubusercontent.com/MiacomSoft/PanoView360_Html/main/img/Tailand_2024/Kata/PIC_20240602_101450.jpg)
<br/>[Панорама просмотра 4](https://raw.githack.com/MiacomSoft/PanoView360_Html/main/viewer_preview.html?photo=https://raw.githubusercontent.com/MiacomSoft/PanoView360_Html/main/img/Tailand_2024/Kata/PIC_20240602_101514.jpg)
<br/>[Панорама просмотра 5](https://raw.githack.com/MiacomSoft/PanoView360_Html/main/viewer_preview.html?photo=https://raw.githubusercontent.com/MiacomSoft/PanoView360_Html/main/img/Tailand_2024/Kata/PIC_20240602_101517.jpg)
<br/>[Панорама просмотра 6](https://raw.githack.com/MiacomSoft/PanoView360_Html/main/viewer_preview.html?photo=https://raw.githubusercontent.com/MiacomSoft/PanoView360_Html/main/img/07.09.2025/PIC_20250907_152549.jpg)

<br/>[Панорама просмотра с переходами по тосчкам 1](https://raw.githack.com/MiacomSoft/PanoView360_Html/main/viewer.html?photo=https://raw.githubusercontent.com/MiacomSoft/PanoView360_Html/main/img/04.01.2026/DSCN0021.JPG)
<br/>[Панорама просмотра с переходами по тосчкам 2](https://raw.githack.com/MiacomSoft/PanoView360_Html/main/viewer.html?photo=https://raw.githubusercontent.com/MiacomSoft/PanoView360_Html/main/img/07.09.2025/PIC_20250907_152549.jpg)
<br/>[Редактирование точек перехода (двойной клик для добавления точки)](https://raw.githack.com/MiacomSoft/PanoView360_Html/main/edit.html?photo=https://raw.githubusercontent.com/MiacomSoft/PanoView360_Html/main/img/04.01.2026/DSCN0021.JPG)


[Смотреть видео-описание проекта](https://raw.githack.com/MiacomSoft/PanoView360_Html/main/Video/Info.mp4)

## 📖 [О проекте](info.md)

**Panorama360** - это мощное веб-приложение для создания, редактирования и просмотра интерактивных 360-градусных панорамных туров. Проект предоставляет полный набор инструментов для визуального проектирования виртуальных туров без необходимости написания кода.

### 🎯 Ключевые возможности

- **🎨 Визуальный редактор** - Создавайте точки перехода (hotspots) двойным кликом по панораме
- **👁️ Предпросмотр в реальном времени** - Настраивайте направление камеры с помощью интерактивного превью
- **💾 Импорт/Экспорт JSON** - Сохраняйте и загружайте конфигурации туров
- **🔄 Поддержка внешних ресурсов** - Работа с изображениями из любых источников (CORS)
- **📱 Адаптивный интерфейс** - Оптимизирован для десктопов и мобильных устройств
- **🎮 Плавное управление** - Инерция, анимации и интуитивное управление

## 🚀 Быстрый старт

### Установка

1. Клонируйте репозиторий:
```bash
git clone https://github.com/MyasnikovIA/Pano360.git
```

2. Откройте в браузере:
- `edit.html` - Редактор туров
- `viewer.html` - Плеер
- `viewer_preview.html` - Инструмент предпросмотра

### Базовое использование

#### Просмотр панорамы
```html
<!-- Простой плеер -->
<iframe src="viewer.html?photo=URL_ПАНОРАМЫ.jpg"></iframe>

<!-- С указанием hotspots -->
<iframe src="viewer.html?photo=URL_ПАНОРАМЫ.jpg&hotSpots=[{...}]"></iframe>
```

#### Создание тура в редакторе
1. Откройте `edit.html`
2. Двойным кликом создайте точку перехода
3. Настройте URL и направление камеры
4. Сохраните и экспортируйте в JSON

## 🏗️ Архитектура проекта

### Структура файлов

```
Pano360/
├── lib/
│   └── Pannellum2_5_6/          # Ядро рендеринга (WebGL)
│       ├── pannellum.js         # Основная библиотека
│       ├── libpannellum.js      # WebGL рендерер
│       └── pannellum.css        # Стили библиотеки
├── lib/app/                      # Компоненты приложения
│   ├── edit.css                 # Стили редактора
│   ├── view.css                 # Стили плеера
│   └── preview.css              # Стили превью
├── edit.html                    # Редактор туров
├── viewer.html                  # Плеер
├── viewer_preview.html          # Инструмент превью
├── panorama_edit.js             # Логика редактора
├── panorama_player.js           # Логика плеера
└── pannellum_preview.js         # Логика превью
```

### Модули приложения

| Модуль | Файл | Назначение |
|--------|------|------------|
| **Редактор** | `edit.html` + `panorama_edit.js` | Создание и редактирование туров |
| **Плеер** | `viewer.html` + `panorama_player.js` | Просмотр готовых туров |
| **Превью** | `viewer_preview.html` + `pannellum_preview.js` | Настройка координат камеры |
| **Ядро** | `lib/Pannellum2_5_6/` | Рендеринг панорам (WebGL/CSS3D) |

## 🛠️ Технологии

### Основные технологии
- **WebGL** - Аппаратное ускорение для плавного рендеринга
- **JavaScript (ES6+)** - Модульная архитектура на классах
- **HTML5 / CSS3** - Современный адаптивный интерфейс
- **Pannellum** - Библиотека рендеринга сферических панорам

### API и протоколы
- **PostMessage API** - Взаимодействие между окнами (iframe)
- **Fetch API / XHR** - Асинхронная загрузка данных
- **FileReader API** - Импорт локальных файлов
- **CORS** - Поддержка внешних ресурсов

## 📦 Формат данных

### JSON схема для тура

```json
{
  "pitchCam": -24.41,           // Направление камеры по вертикали
  "yawCam": -6.77,              // Направление камеры по горизонтали
  "hotSpots": [
    {
      "id": 1234567890,         // Уникальный идентификатор
      "name": "Комната 1",      // Название точки
      "type": "scene",          // Тип: scene, info, custom
      "text": "Перейти в комнату",
      "pitch": -10.5,           // Координата клика (вертикаль)
      "yaw": 30.2,              // Координата клика (горизонталь)
      "targetPitch": 0,         // Направление камеры после перехода
      "targetYaw": 0,
      "panorama_url": "https://example.com/panorama.jpg",
      "createdAt": "2026-01-01T12:00:00Z"
    }
  ]
}
```

## 🎮 Управление

### Клавиатурные комбинации

| Клавиша | Действие |
|---------|----------|
| `W` / `↑` | Вращение вверх |
| `S` / `↓` | Вращение вниз |
| `A` / `←` | Вращение влево |
| `D` / `→` | Вращение вправо |
| `+` / `-` | Приблизить / Отдалить |
| `Esc` | Выход из полноэкранного режима |

### Мышь и сенсор

- **Перетаскивание** - Вращение панорамы
- **Колесо мыши** - Приближение/отдаление
- **Двойной клик** - Создание точки (в редакторе)
- **Правая кнопка** - Контекстное меню (в редакторе)
- **Жесты** - Панорамирование и масштабирование

## 📱 Мобильная поддержка

Проект оптимизирован для мобильных устройств:
- Поддержка сенсорных жестов
- Адаптивные размеры точек (hotspots)
- Автоматическое определение устройства
- Оптимизация производительности

## 🔧 Разработка и расширение

### Создание собственного инструмента

```javascript
// Инициализация плеера
const player = new PanoramaPlayer('canvas', 'path/to/panorama.jpg');

// Инициализация редактора
const editor = new PanoramaEditor();

// Добавление кастомного обработчика
player.on('load', () => {
    console.log('Панорама загружена!');
});
```

### Расширение функциональности

Проект построен на модульной архитектуре, что позволяет легко добавлять новые функции:
1. Наследуйте классы `PanoramaPlayer` или `PanoramaEditor`
2. Добавляйте новые методы и обработчики
3. Расширяйте интерфейс через CSS

## 🤝 Вклад в проект

Мы приветствуем вклад в развитие проекта!

1. Форкните репозиторий
2. Создайте ветку для новой функции (`git checkout -b feature/amazing-feature`)
3. Внесите изменения
4. Создайте Pull Request

### Планы развития

- [ ] Поддержка видео-панорам
- [ ] Расширенная анимация переходов
- [ ] Интеграция с картами (Google Maps)
- [ ] Экспорт в HTML (самостоятельный файл)
- [ ] Редактор маршрутов
- [ ] Поддержка VR-шлемов

## 📄 Лицензия

Распространяется под лицензией MIT. Подробности в файле `LICENSE`.

## 🌟 Благодарности

- [Pannellum](https://pannellum.org/) - Замечательная библиотека для панорам
- Сообществу разработчиков за идеи и поддержку

## 📧 Контакты

- Автор: MyasnikovIA
- Проект: [GitHub](https://github.com/MiacomSoft/PanoView360_Html)

---

<p align="center">
  Сделано с ❤ для создания удивительных виртуальных туров
</p>
