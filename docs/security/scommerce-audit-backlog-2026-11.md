# sCommerce: зіставлення історичного аудиту з кодом і backlog

Дата статичного перегляду: **10.11.2026**. Документ містить аналіз і завдання для майбутніх виправлень. Реалізацію та описані нижче тести не виконано.

## 1. Перевірений зріз і межі доказів

| Об’єкт | Зафіксований стан |
| --- | --- |
| Корінь пакета | `H:/PhpstormProjects/sCommerce`, Composer package `seiger/scommerce` |
| Branch | `1.x` |
| Commit | `9e9d601815e4944d594143e41c4bf2a0107559f9` |
| Робоче дерево перед аналізом | Чисте: `git status --porcelain=v1` не повернув записів |
| Інструкції | Надані користувачем AGENTS; локальні `MiddleSokil/AGENTS.md`, `SokilBook/AGENTS.md`, `CONSTITUTION.md`, `SokilBook/contracts/WRITING-CONTRACT.md`; `docs/README.md`. У перевірених каталогах пакета власних AGENTS.md не знайдено |
| Місце документа | Окремого усталеного каталогу аудитів у поточному дереві `docs/` не знайдено. Використано запитаний `docs/security/`; цільовий файл до створення був відсутній |
| Залежності пакета | `composer.json:22–28`: Evolution CMS `^3.5.8`, sTask `^2.0`; у корені пакета немає `vendor/` і `composer.lock`, які фіксували б фактичний deployment |

Історичне джерело — **витяг, наданий користувачем** у `Вставлений текст.txt`. Сам архів від 06.11.2026 не був наданий; у перевірених локальних матеріалах MiddleSokil повних карток не знайдено. Зі слів витягу, у зрізі 04.11 шість прямих карток мали `CONFIRMED`, H-033 — `PARTIAL`; 06.11 оновлювалися посилання й рядки. Це не новий аудит. `CONFIRMED` у цьому документі означає історичну наявність конструкції, **не доведену експлуатацію**. Для варіантів H-033 не успадковується автоматично ні `PARTIAL`, ні підтвердження вразливості. Для пов’язаних H-022 і M-038 витяг не задає окремого точного статусу; I-006 історично `NOT_DETERMINED`.

Використано лише читання локальних файлів, пошук, Git metadata і хешування файлів. Не запускалися застосунок, Composer scripts, міграції, імпорт, черги, платіжні операції, листи, HTTP-запити або експлуатаційні перевірки. Не виконувалися fetch/pull/reset/clean, commit, push чи створення PR. Код, тести, залежності та конфігурацію не змінено. Права процесу PHP на deployment, записи БД, установлені gateway, reverse proxy, ефективні cookies і runtime middleware не перевірені.

### Додаткові локальні джерела

Ці джерела допомагають простежити межі пакета, але **не доводять**, що саме така комбінація встановлена на сайті з перевіреним sCommerce commit.

| Позначення у file:line | Абсолютний корінь і походження |
| --- | --- |
| Без префікса | `H:/PhpstormProjects/sCommerce/`, commit вище |
| `E36/` | `H:/PhpstormProjects/Extras/MiddleSokil/evolution-cli-eof-20261102/`; чистий checkout `8e43927f5c4f41b51996ec546416abfa2a410cdf`, `E36/core/factory/version.php:2` містить `3.6.0` |
| `D35/` | `H:/PhpstormProjects/dc-ukraine/`; локальний приклад інтеграції, `D35/core/factory/version.php:2` містить `3.5.8`; це може бути модифікований код, не еталон релізу |
| `TASK/` | `D35/core/vendor/seiger/stask/`; у `D35/core/composer.lock` зазначено `v2.1.0`, source reference `624d0fe6c42acee9f2ef77491f1494bf8ea01549`; відповідність усіх vendor-файлів upstream не встановлювалася |
| `PRO/` | `H:/PhpstormProjects/sCommercePro/core/custom/packages/seiger/src/sCommercePro/`; repository commit `bc0b282ae1805a7f8c353c7f95e87fe91b51dc70`, branch `main`; у тому репозиторії вже був untracked `.idea/`, його не змінено |

Для відтворюваності локального D35/TASK-зрізу зафіксовано SHA-256:

| Файл | SHA-256 |
| --- | --- |
| `D35/core/config/app.php` | `FEA5A2DA63A0C32D3E2C134EE708670FA9C5C8413C54F896B8D473FA206D63EC` |
| `D35/core/src/Middleware/VerifyCsrfToken.php` | `D67BE59C0119201BF94F163FFA4CAF74D0776E230326440AC6DB3024739E1835` |
| `TASK/src/Controllers/sTaskActionController.php` | `A25EB26BE632ACC798C3FE12ED31422D692398033A5B84B3478BF84BA806595C` |

У D35 lock указано sCommerce `v1.3.7`, reference `da2a4b1defcad682e41de828418c4cadaad25d50`. Тому D35 не використано як доказ deployment поточного checkout. Джерела E36, D35, TASK і PRO лише читалися.

## 2. Зведення семи прямих карток

| Історичний ID | Статус 04.11 за витягом | Поточний статичний висновок | Пункти backlog |
| --- | --- | --- | --- |
| C-010 | CONFIRMED | Request → власний серіалізатор → PHP-файл зберігся. SQL і ACL винесені в єдині відповідні пункти. Перевірені виводи імені покупця вже escaped; manager-повтор має захисти | B01, B02, B03; історія R02–R03; окремо B21 |
| H-031 | CONFIRMED | Пакет покладається на `web`. У двох конкретних локальних ядрах типова `web` без CSRF, `mgr` із CSRF. Токен у query string присутній | B04–B05 |
| H-032 | CONFIRMED | Filename із metadata/options потрапляє до файлового API без containment. Переглянутий sTask приймає request options; повної гарантії прав виконання немає. Parser options без власної перевірки | B06–B07 |
| H-033 | PARTIAL | Робочий payment dispatcher є. Старий integration controller відсутній, але його route declarations залишилися. Платіжні підсценарії мають різні результати: Stripe/Portmone із небезпечними шляхами, інші gateway мають перевірку підпису | B08–B17, B22; історія R01, R05 |
| H-034 | CONFIRMED | Обидва search scopes інтерполюють пошукові рядки в raw SELECT; manager-виклики встановлено. Публічна досяжність поточного checkout залежить від інтеграції сайту | B18–B19 |
| H-035 | CONFIRMED | Raw REPLACE для catalog_root зберігся. Мовні SQL-фрагменти в CSV збереглися; низка інших мовних запитів уже має bindings | B03, B20; історія R04 |
| M-019 | CONFIRMED | Право `settings` перевірено для вкладки й відкриття settings, але не для прямої дії settingsSave. Доступ до module dispatcher окремо контролює ядро | B02 |

Це зіставлення семи карток, а не підрахунок нових вразливостей за кількістю рядків таблиці. Незалежні чутливі операції розділено нижче; дублікати C-010/H-035/M-019 не створюють додаткових задач.

### Пріоритети

- **P0** — першочергова зміна конструкції з потенційним виконанням PHP або прийманням недовіреної платіжної події. Для PRO-пунктів застосовність умовна: gateway має бути встановлений і викликатися.
- **P1** — виправлення SQL, авторизації, CSRF, файлових меж і платіжних інваріантів після звірки deployment.
- **P2** — обмеження вводу, усунення витоку токена, уточнення контрактів і сценарії, де шлях до експлуатації ще не встановлено.

Пріоритет визначає порядок роботи, не замінює оцінку експлуатованості чи CVSS.

## 3. Спільні шляхи dispatch і захисти

### Manager

`src/sCommerceServiceProvider.php:224–239` реєструє file module без власного переліку `roles`. `src/sCommerce.php:557–565` будує URL `index.php?a=112&id=<module-key>` і додає `_token` для manager-сесії. У E36 `E36/core/src/ManagerTheme.php:475–481` реєструє `mgr`, а `E36/core/config/app.php:151–157` містить session, SessionProxy, VerifyCsrfToken і Manager. `E36/manager/processors/execute_module.processor.php:6–10,50–69,84` перевіряє manager mode, `exec_module`, реєстраційний ключ, `ModuleAccess::canRunFileModule`, читабельність файла й лише тоді виконує module code. `E36/core/src/Support/ModuleAccess.php:39–50,83–105` трактує порожній список roles як відсутність додаткового обмеження за роллю.

Після цього `module/sCommerceModule.php:41–48,126` має guard manager mode, читає request `get` і виконує відповідний `case`. Немає загального whitelist дій, що походить від дозволених вкладок. Наявність `exec_module` і права на конкретну зміну — різні перевірки. I-006 залишається історично `NOT_DETERMINED`: із цих даних не випливає анонімний доступ до модуля. D35 processor також перевіряє `exec_module`, але не має E36-перевірки `ModuleAccess` для file module; це різниця локальних зрізів, а не доказ поточного стану всіх інсталяцій.

### Браузерні маршрути

`src/sCommerceServiceProvider.php:45–46,125–127` завантажує `src/Http/routes.php`. `src/Http/routes.php:11–65` задає POST cart/checkout/pay-order/quick-order/wishlist і friendly-suffix варіанти під `web`, без власного CSRF middleware або auth guard. Стан cart/checkout/wishlist пов’язаний із сесією: `src/Cart/sCart.php:217–227`, `src/Checkout/sCheckout.php:400–409`, `src/Wishlist/sWishlist.php:56–62,77–91`.

`E36/core/src/Core.php:3196–3217` формує `web` із `app.middleware.global` + `middleware.global`, а `E36/core/config/app.php:170–175` не включає CSRF. Те саме видно у `D35/core/src/Core.php:2952–2974` і `D35/core/config/app.php:142–146`. Додатковий `D35/core/custom/config/middleware.php:3–5` додає лише SetStorefrontCurrency. У E36 `VerifyCsrfToken.php:106–128` і D35 `VerifyCsrfToken.php:20–22` перевірка додатково залежить від `mgrValidated`; сам alias `csrf` не доводить захисту звичайної storefront-сесії.

Немає підстав переносити стандартний Laravel web stack на Evolution CMS за назвою групи. Водночас для невідомого deployment залишаються відкритими overrides, route cache та cookie policy. У D35 `D35/core/config/session.php:155,167,183` задано `secure` через env, HttpOnly і SameSite=Lax; ефективні значення й альтернативні session cookies не перевірені. SameSite та поведінка браузера впливають на cross-site сценарій, але не є доказом серверної перевірки токена.

### Payment callbacks

`src/Http/routes.php:68–79` приймає GET/POST `payment-callback/{method?}` під `web`. `src/sCommerce.php:680–715` визначає метод, завантажує клас із payment settings і делегує `handleWebhook()`. `src/sCommerce.php:732–744,747–798` сортує active першими, але не фільтрує всі варіанти через `active()`; це також потребує явної політики щодо callback для вимкненого методу. `matchesWebhookSettings()` на `src/sCommerce.php:803–852` перевіряє наявність полів; `matchesLegacyWebhookSignature()` на `879–887` лише розпізнає формат. Це **маршрутизація, не криптографічна перевірка**.

Підпис, прив’язка платежу й повторна обробка оцінюються всередині gateway. Provider callback не повинен вимагати browser CSRF-токен; для нього потрібна власна автентифікація події. Убудовані Cash/BankInvoice не мають `handleWebhook`; dispatcher у такому випадку повертає 501 (`src/sCommerce.php:710–711`).

## 4. Backlog

У всіх пунктах «тести» означає **сценарії для майбутньої реалізації**, яких під час цього аналізу не запускали. Статичний висновок стосується наведеної операції та її передумов.

### B01 — замінити генератор виконуваної PHP-конфігурації

- **Історія:** C-010, CONFIRMED. **Зараз:** небезпечна конструкція збереглася; виконання довільного коду на deployment не перевірено. **Пріоритет: P0.**
- **Докази й потік:** `module/sCommerceModule.php:2620–2622` → `src/Controllers/sCommerceController.php:130–205`: request keys із префіксами basic/cart/constructor/currencies/notifications/orders/product/products → `dataToString()` → `fopen(...sCommerce.php, "w")`/`fwrite`. Другий вхід: `module/sCommerceModule.php:2277–2278` → `sCommerceController.php:76–117` → `sCommerceCurrencies.php`. `dataToString()` на `1097–1120` перетворює `var_dump` регулярними виразами; немає коректного PHP literal escaping значень і ключів. Видалення/заміна дужок та пробілів не забезпечує серіалізацію.
- **Підключення результату:** пакет читає `config('seiger.settings...')`, зокрема валюти на `src/sCommerce.php:378`. У D35 `D35/core/src/AbstractLaravel.php:177–178,220–244` рекурсивно завантажує `custom/config/*.php` через `require`; отже згенерований PHP стає виконуваним кодом у такій інтеграції. Перевірений `documentListing():395` підключає інший cache-файл і не використовується як доказ цього sink.
- **Права й передумови:** manager із доступом до module dispatcher; для settingsSave окремо B02. Процес PHP має мати право запису в фіксований `EVO_CORE_PATH/custom/config/seiger/settings/`, а наступне завантаження — читати результат. Фактичні ACL/owner, umask, PHP runtime та момент reload невідомі. Шлях файла фіксований; filename від request тут не залежить.
- **Наявні захисти:** фільтрація префіксів, часткові numeric casts, slug ключа конструктора (`140`), окрема чистка marketing fields (`162–163`), manager/core CSRF. Вони не є загальним escaping рядків чи allowlist налаштувань.
- **Напрям:** schema/allowlist і типи ключів; стандартна безпечна серіалізація літералів або data-only формат; атомарний запис із перевіркою помилок, обмеженими правами та без частково записаного конфігу. Узгодити спосіб завантаження з ядром.
- **Приймання й регресії:** довільні лапки, backslash, долари, Unicode, переноси рядків, вкладені масиви й ключі залишаються даними; збережений результат відтворює значення без побічних виконань; невідомі ключі/типи відхиляються; відмова запису й паралельне збереження не пошкоджують останній валідний конфіг. Перевірити обидва файли й обидва dispatch-входи.

### B02 — перевіряти право settings у settingsSave

- **Історія:** M-019, CONFIRMED; ACL-варіант C-010 — цей самий сценарій. **Зараз:** серверний розрив між переглядом і збереженням підтверджений статично. **Пріоритет: P1, разом із B01/B03.**
- **Докази й потік:** `module/sCommerceModule.php:121–123` приховує settings tab; `2601–2605` захищає відкриття settings. Прямий request `get=settingsSave` іде через загальний switch (`48,126`) до `2620–2626`: DB config, PHP config, event і clearCache без `hasPermission('settings')`. Немає переходу через guard із case settings.
- **Права й передумови:** авторизований manager, право `exec_module`, доступ до file module та чинний CSRF у ядрі, яке його вимагає. Це не анонімний endpoint. Невідомо, чи на конкретному сайті існує роль із доступом до sCommerce без `settings`, і чи plugin не додає власний guard.
- **Захисти:** module access, manager mode й core CSRF зі спільного dispatch. Вони не перевіряють право `settings` для цієї дії.
- **Напрям:** спільний серверний guard для view/save settings, POST-only мутація до будь-якого sink/event; погоджена політика прав для currenciesSave, не дублюючи B16.
- **Приймання й регресії:** manager із module access, але без settings, отримує відмову при прямому запиті; файли/БД/cache/events незмінні. Дозволений manager із валідним токеном зберігає; anonymous, неправильний метод і відсутній токен не виконують зміну. Окремо перевірити `exec_module`/ModuleAccess, а не вважати їх заміною action permission.

### B03 — параметризувати basic__catalog_root у REPLACE

- **Історія:** H-035, CONFIRMED; SQL у settings із C-010 — дублікат. **Зараз:** шлях request → raw SQL встановлено. **Пріоритет: P1.**
- **Докази й потік:** settingsSave → `src/Controllers/sCommerceController.php:55–64`: `request()->input('basic__catalog_root')` без integer validation → інтерполяція у SQL string `REPLACE INTO system_settings` на `62` → `setConfig` на `63`.
- **Права/передумови:** manager/module access зі спільного dispatch; розширення кола через B02. DB-з’єднання повинно мати право зміни system_settings; SQL dialect/driver, flags і додаткові захисти БД невідомі. HTML-поле форми не обмежує прямий request.
- **Захисти:** фіксоване ім’я setting і table name від ядра; перевірка зміни значення. Для значення немає binding або escaping. Немає доказу конкретного наслідку ін’єкції на працюючій БД.
- **Напрям:** integer/range/existence validation catalog root і query builder/bindings; авторизація B02 до виклику.
- **Приймання й регресії:** позитивний дозволений resource ID зберігається; лапки, SQL-пунктуація, масив, від’ємне/неіснуюче значення відхиляються; SQL-текст сталий, ввід лише в bindings; нуль/порожнє значення мають явно погоджену семантику. Не створювати другий SQL-ticket під C-010.

### B04 — захистити браузерні cart/checkout/payment/wishlist мутації

- **Історія:** H-031, CONFIRMED. **Зараз:** у пакеті є залежність від незахищеної типової web-групи в перевірених E36 і D35. Остаточний висновок для іншого deployment відкритий. **Пріоритет: P1.**
- **Докази й потік:** `src/Http/routes.php:11–65` → cart/session (`src/Cart/sCart.php:72–142,227`), checkout session і створення order (`src/Checkout/sCheckout.php:366–409,432–469,864–904`), прямий payOrder (`743–760`), wishlist (`src/Wishlist/sWishlist.php:29–62`). Middleware/session chain наведено в розділі 3.
- **Клієнт:** `views/scripts/cart.blade.php:129–142,167–174,193–199` надсилає FormData з X-Requested-With без токена; `views/scripts/global.blade.php:2–30` має X-CSRF-TOKEN, а wishlist використовує цей helper (`views/scripts/wishlist.blade.php:39`). Надсилання заголовка не створює серверної перевірки; X-Requested-With не перевіряється в наведених route closures.
- **Права/передумови:** активна сесія жертви й умови доставки cookies у браузері; login не обов’язковий для cart/checkout. Wishlist може змінити також запис user_attributes для поточного user. JSON/CORS, SameSite, iframe/site topology та method/content-type впливають на cross-site досяжність і не перевірені runtime.
- **Захисти:** POST, валідація товарів/checkout, часткове надсилання токена, cookie-політика конкретного сайту. У E36/D35 manager-only CSRF не є достатнім storefront guard. Package exceptions не знайдені; provider callbacks розглядаються окремо, а не як причина виключити всю групу.
- **Напрям:** окремий fail-closed CSRF для browser-session endpoints, сумісний із session layer Evolution; оновити всі frontend виклики, включно з friendly suffix. Зберегти callback authentication окремою політикою.
- **Приймання й регресії:** для кожного browser endpoint запити без токена, з чужим/простроченим/масивним токеном не змінюють стан; правильний токен працює для guest і logged-in сесій; перевірити form/JSON та suffix routes. Provider callback з валідним підписом працює без browser token; непідписаний не змінює платіж. Матрицю виконати з фактичною версією ядра та overrides.

### B05 — прибрати CSRF-токен із manager URL

- **Історія:** окремий варіант H-031, картка CONFIRMED. **Зараз:** передача в query string присутня. **Пріоритет: P2.**
- **Докази й потік:** `src/sCommerce.php:557–565` додає `_token` до moduleUrl; URL використано в links/form actions, наприклад `views/ordersTab.blade.php:344`, `views/reviewTab.blade.php:7`, `views/settingsTab.blade.php:122`.
- **Передумови/наслідок:** manager-сесія. Токен може потрапляти в browser history, server/proxy logs і, залежно від Referrer-Policy та переходу, referrer. Реального витоку сторонній особі не встановлено. HTTPS сам по собі не прибирає токен із URL.
- **Захисти/невідоме:** rawurlencode; токен прив’язаний до сесії; log redaction і Referrer-Policy невідомі. E36 вимагає CSRF і для mutating GET `a=112`, тому просте видалення query token може зламати manager navigation.
- **Напрям:** узгодити з core розділення читання й POST-мутацій; токен у body/header, без його повернення в redirect/links. Це окреме завдання сумісності, не механічна заміна URL.
- **Приймання й регресії:** module links/redirects/form URLs без `_token`; body/header токен перевіряється сервером; відкриття вкладок і дозволені операції працюють у підтриманих ядрах; GET не змінює стан. Перевірити сформовані URL, history/log fixtures без використання справжніх токенів.

### B06 — обмежити CSV filename каталогом та об’єктом upload

- **Історія:** H-032, CONFIRMED. **Зараз:** неконтрольований файловий шлях у worker підтверджено; розкриття довільного вмісту сторонній особі не доведено. **Пріоритет: P1.**
- **Докази й потік:** `views/partials/widgetImportExportCSV.blade.php:85–90` upload → POST worker run із filename. У переглянутому TASK `src/Http/routes.php:8–26` використовує mgr; `TASK/src/Controllers/sTaskActionController.php:79–89` передає direct/nested options у createTask; `TASK/src/Workers/BaseWorker.php:353–384` зберігає їх у meta; `TASK/src/Models/sTaskModel.php:94–106` лише нормалізує порядок ключів. Далі `src/Integration/ImportExportCSV.php:488–500` бере meta.filename/options.filename: існуючий шлях приймається через realpath, інакше додається до `storage_path('stask/uploads/...')`; `733–754` відкриває та читає його.
- **Передумови/права:** можливість створити/змінити task metadata або передати worker options; зареєстрований активний worker і доступний файл для OS-користувача worker. TASK resolver (`sTaskActionController.php:609–627`) перевіряє active class і TaskInterface, не file ownership. У run немає окремого `hasPermission('stask')`; UI-guard sCommerce integrations на `module/sCommerceModule.php:2259` не захищає прямий route. Ефективна manager-автентифікація для окремих mgr routes залежить від Manager action/hasManagerAccess ядра, тому анонімний запуск не доведений і має бути перевірений окремо.
- **Захисти й прогалини:** upload генерує filename у storage (`TASK/.../sTaskActionController.php:519–529`) і перевіряє upload; run приймає filename незалежно від цього upload. Немає canonical containment, заборони absolute/traversal, перевірки symlink target, regular file, зв’язку upload із користувачем/task. `realpath` лише канонізує шлях, не обмежує його коренем. Потік веде до CSV parsing і можливих product writes/logs; generic download endpoint не доводить видачу імпортованого джерела.
- **Напрям:** server-side upload ID замість довільного path; авторизація запуску; прив’язка upload до користувача й задачі; canonical root + canonical target containment із урахуванням platform separators/case і symlinks; читання лише regular CSV-файлів, обмеження часу життя/розміру та захист від підміни між перевіркою і відкриттям.
- **Приймання й регресії:** відхиляються absolute/UNC/drive paths, `../`, вкладений traversal, змішані separators, sibling prefix, symlink назовні й чужий upload; дозволений файл читається; помилки не розкривають contents/секретні шляхи. Окрема матриця anonymous/manager без stask/дозволений manager для worker run. Перевірки лише у майбутньому тестовому каталозі.

### B07 — валідувати CSV parser options перед worker execution

- **Історія:** додатковий варіант H-032, картка CONFIRMED; окремий історичний статус параметрів у витягу відсутній. **Зараз:** defaults можна перекрити options без локальної перевірки. **Пріоритет: P2.**
- **Докази й потік:** TASK request options із B06 → `src/Integration/ImportExportCSV.php:478–485` array_merge → `fgetcsv` на `754,761,771,797` і `fputcsv` на `530–534`. Перед ними немає перевірки типу/довжини delimiter/enclosure/escape. batch_size/use_temp_table також надходять із options; large/temp-table гілка на `509–515` містить закоментований виклик обробки.
- **Передумови/права:** право запускати CSV worker або інший довірений виклик taskImport/taskExport. Неправильні параметри можуть спричинити помилки парсера/некоректну обробку; DoS на інсталяції не доведений. Чи нормалізує опції інша версія sTask — невідомо.
- **Захисти:** defaults і обробка частини винятків; `taskImport` ловить Exception (`517–519`), що не доводить контроль усіх Error/TypeError/ValueError.
- **Напрям:** allowlist options, типи й byte-length обмеження, допустимі комбінації, розмір/кількість рядків; відхиляти непідтриману temp-table гілку явно.
- **Приймання й регресії:** empty/multibyte/multi-character separator, array/null options, несумісний escape, крайні batch sizes і невалідні boolean не створюють завислих задач або часткового success; валідні CSV із BOM/Unicode/quoted fields обробляються передбачувано. Окремо великий файл не отримує удаваного успіху без імпорту.

### B08 — Stripe: обов’язково перевіряти підпис callback

- **Історія:** H-033, картка PARTIAL; точний статус цього варіанта у витягу не наведений. **Зараз:** у перевіреному PRO підтверджено fail-open гілку. **Пріоритет: P0 за наявності цього gateway.**
- **Докази й потік:** payment-callback → `src/sCommerce.php:680–715` → `PRO/Payment/StripePayment.php:322–345`: constructEvent виконується лише якщо secret **і** signature непорожні; інакше JSON декодується без автентифікації. `360–369` бере order ID з client_reference_id/metadata; `378–422` передає amount/currency/event у PaymentFlow та зберігає стан; `423–429` може записати failed.
- **Права/передумови:** доступний callback, зареєстрований gateway class, потрібний SDK і наявне замовлення; для fail-open достатньо відсутності одного з secret/signature. Чи встановлено саме PRO commit, чи callback додатково фільтрується на deployment — невідомо. Runtime-підроблення не виконувалося.
- **Захисти:** constructEvent є для обох заповнених полів; parse exceptions і missing-order handling. Вони не закривають else-гілку. Не переносити цей висновок на інші gateway.
- **Напрям:** відмовляти до читання бізнес-полів без webhook secret/підпису; перевірка raw body SDK, account/environment і версії події; жодного production fallback до unsigned JSON.
- **Приймання й регресії:** secret missing, header missing/empty, неправильний підпис, змінений raw body, старий timestamp не змінюють БД/ledger/history; валідна SDK-signed fixture приймається. Окремо перевірити explicit method і auto-detection. Усі fixtures локальні, зовнішні платежі не потрібні.

### B09 — Portmone: автентифікувати подію до зміни payment_status

- **Історія:** H-033, картка PARTIAL; підсценарій у витягу не деталізований. **Зараз:** переглянутий PRO-шлях не містить перевірки походження callback до запису. **Пріоритет: P0 за наявності цього gateway.**
- **Докази й потік:** `PRO/Payment/PortmonePayment.php:292–314` parse_str raw body → `processCallback()`; `360–400` шукає order за SHOPORDERNUMBER і за RESULT записує PAID, `406–417` — FAILED. Є другий вхід через `payButton():86–102`, який також читає raw request body та викликає processCallback. На цих шляхах немає підпису або server-to-server перевірки події до update.
- **Права/передумови:** відповідний gateway і callback або сторінка, яка викликає його renderer; знання order identifier. Deployment і правила провайдера не встановлено; факт зарахування фальшивого платежу runtime не доведений.
- **Захисти/невідоме:** наявність SHOPBILLID, пошук order, exception logging; BILL_AMOUNT зберігається, але не звіряється з order cost на наведеному шляху. Існування makeApiRequest на `320–354` не доводить його використання у callback — processCallback його не викликає.
- **Напрям:** підтвердження походження за фактичним протоколом провайдера та обов’язкова звірка merchant, transaction, order, суми/валюти; відокремити renderer від callback. Додати idempotency й дозволені переходи станів у цьому ж шляху, не створюючи дублікат за H-031.
- **Приймання й регресії:** недовірені/непідтверджені дані з обох входів не змінюють order; невідповідна сума/merchant/order відхиляються; повтор і reordered callback не змінюють фінальний стан та history повторно; валідна provider fixture дає один перехід. Протокол звірити перед реалізацією, без припущення про конкретний алгоритм підпису.

### B10 — Stripe: прив’язка події, ідемпотентність і переходи стану

- **Історія:** H-033, картка PARTIAL; окремий історичний результат невідомий. **Зараз:** часткові захисти є; повного інваріанта не видно. **Пріоритет: P1 після B08.**
- **Докази й потік:** `PRO/Payment/StripePayment.php:360–429` обирає order із події, бере amount/currency, записує captured або failed; `549–574` savePaymentInfo змінює статус без state-transition policy. `src/Payment/PaymentFlow.php:80–128` dedup за provider+provider_ref; `database/migrations/2026_03_01_000000_create_order_payments_table.php:36–40` задає unique index; `PaymentFlow.php:210–238,252–289` обчислює projection із ledger.
- **Передумови/права:** валідно автентифікована подія або інший довірений виклик gateway/PaymentFlow. Відсутність B08 не є необхідною умовою replay/reordering; це окремий сценарій. Фактична наявність таблиці/index не перевірена; ledgerAvailable має legacy fallback.
- **Захисти/прогалини:** nonempty event ID dedup і unique constraint — реальний захист від дубля captured. Порожній event ID передається як null; для failed-гілки цього dedup немає. Не видно звірки з очікуваними session/payment intent, merchant method, paid state, currency й сумою замовлення перед mutation; duplicate lookup не звіряє order_id. Паралельний unique conflict і атомарність ledger + payment_info потребують перевірки.
- **Напрям:** стабільний nonempty provider event/transaction ID, inbox із транзакційним dedup, прив’язка до очікуваної payment attempt, перевірка amount/currency та monotonic policy станів з явними refund/dispute правилами.
- **Приймання й регресії:** одна подія послідовно/паралельно дає один ledger entry; інший order/session/currency/amount не змінює замовлення; success→старий failure не скасовує оплату; пустий event ID відхиляється; legacy і ledger режими мають узгоджену політику. Повтор не множить events/history.

### B11 — WayForPay: replay, прив’язка й допустимі переходи

- **Історія:** H-033, картка PARTIAL. **Зараз:** перевірка підпису є; replay/state/amount binding неповні на переглянутому шляху. **Пріоритет: P1 за використання gateway.**
- **Докази й потік:** `PRO/Payment/WayForPayPayment.php:586–650` вимагає поля й hash_equals для підпису, який охоплює account/reference/amount/currency/status та інші поля. `653–702` знаходить order за identifier, із fallback на numeric ID, переносить amount/currency/status і додає history, потім save.
- **Права/передумови:** валідно підписана подія, включно з раніше отриманою. У цьому сценарії не доведено можливість підробити підпис. Немає видимого durable event dedup, звірки підписаної суми/валюти з очікуваним платежем та guards від downgrade/repeated history.
- **Захисти/невідоме:** HMAC-підпис/constant-time compare й missing-order handling; algorithm/protocol compatibility та ефективні credentials не перевірялися. Перевірка підпису сама по собі не доводить захист від replay.
- **Напрям:** прив’язати reference до конкретної спроби/методу/merchant; звіряти amount/currency, усунути неоднозначність numeric fallback; idempotency та policy станів під транзакцією.
- **Приймання й регресії:** неправильний підпис не змінює БД; одна валідна подія повторно не додає history; старий failed не перекриває paid; mismatched account/reference/amount/currency не змінює order; сумісні валідні переходи працюють.

### B12 — Monobank: прив’язка invoice і replay/state policy

- **Історія:** H-033, картка PARTIAL. **Зараз:** підпис перевіряється; у звичайного Monobank є сумнівна умова зміни стану, не доведено replay safety. **Пріоритет: P1 за використання gateway.**
- **Докази й потік:** `PRO/Payment/MonobankPayment.php:212–239,462–488` вимагає X-Sign і openssl_verify raw payload. `248–305` шукає order за reference з callback, записує invoiceId/amount і mapped status. На `285` payment_info вже присвоєно; порівняння invoice ID на `290` читає щойно присвоєне значення, тому ця частина умови не захищає від зміни стану для іншої/старої спроби. `295–305` може знову додати history.
- **Права/передумови:** автентична signed provider event або replay такої події; правильний public key. Несанкціоноване отримання/підроблення події не доведено. Public key отримується через helper; мережу та правильність provider protocol не перевіряли.
- **Захисти/невідоме:** криптографічна перевірка до mutation, перевірка invoice/reference. На наведеному mutation-шляху немає явної звірки з раніше збереженою invoice attempt, durable dedup і суми/валюти; підтримка інших обмежень провайдера невідома.
- **Напрям:** порівнювати incoming invoice із попередньою збереженою спробою до merge; явні переходи та ідемпотентність; перевірка merchant/order/amount/currency.
- **Приймання й регресії:** invalid X-Sign без змін; valid replay і stale invoice не змінюють payment/history повторно; pending/failed старої спроби не перекриває paid; current invoice із правильною сумою дає погоджений перехід.

### B13 — перевірити платіжні інваріанти installment gateway окремо

- **Історія:** H-033, картка PARTIAL; точних старих підкарток немає. **Зараз:** частково перевірені захищені шляхи; це завдання завершення перевірки, не встановлена відсутність підпису. **Пріоритет: P2.**
- **Докази:** MonobankInstallment: `PRO/Payment/MonobankInstallmentPayment.php:418–444,846–861` перевіряє HMAC/hash_equals; `452–488` звіряє збережені order_id/method; `511–549` змінює payment status і save. PrivatbankInstallment: `PRO/Payment/PrivatbankInstallmentPayment.php:846–864,887–902` перевіряє storeId і signature; `912–945` звіряє order_id/method; `990–1024` порівнює статус і зберігає. Однакова задача перевірки має дві незалежні gateway-матриці, результати не переносяться між ними.
- **Передумови/права:** установлені gateway, відповідні credentials і signed callback. Наявні signature gates й attempt binding істотно відрізняють ці шляхи від B08/B09. Повного перегляду всіх fallback-пошуків, timestamp/replay/durable dedup і переходів для цих великих класів не завершено; анонімну мутацію не встановлено.
- **Напрям:** окремо для кожного класу завершити статичну перевірку state machine, signed fields, event identity, amount/currency та всіх fallback branches; виправляти лише доведені порушення.
- **Приймання й регресії:** задокументована для кожного gateway матриця unsigned/invalid/replay/out-of-order/cross-order/wrong amount; rejected cases без mutation, valid event застосовується один раз; fallback не обходить звірку method/attempt. Не оголошувати обидва gateway виправленими через успіх одного тесту.

### B14 — pay-order: серверно завантажувати й авторизувати замовлення

- **Історія:** H-033, картка PARTIAL. **Зараз:** request передається в gateway як order data без перевірки в dispatcher; конкретний ефект залежить від gateway. **Пріоритет: P1.**
- **Докази й потік:** `src/Http/routes.php:27–36,51` → `src/Checkout/sCheckout.php:743–760`: весь request → active registered payment method → processPayment. У `PRO/Payment/WayForPayPayment.php:95–109` cost/identifier/currency/products беруться з цього array; у `PRO/Payment/MonobankPayment.php:96–114` cost стає invoice amount, далі створюється provider invoice. Це статично встановлений шлях до зовнішньої операції, яку не запускали.
- **Передумови/права:** browser route, активний відповідний метод і credentials. У загальному payOrder немає order lookup, owner/session capability, перевірки його поточного payment status або server-calculated amount. Деталі provider API й те, чи досягнеться фінансова шкода, не встановлені.
- **Захисти:** список активних payment methods у `src/Checkout/sCheckout.php:836–851`, gateway checks позитивної суми/credentials. Cash (`src/Payment/CashPayment.php:119–121`), BankInvoice (`BankInvoicePayment.php:233–237`), PRO Stripe (`StripePayment.php:213–215`) і Portmone (`PortmonePayment.php:163–165`) тут повертають bool без mutation; не приписувати їм інвойс-створення на цьому вході.
- **Напрям:** приймати лише reference/order ID і мінімальні дозволені параметри; завантажити order, перевірити owner або scoped guest capability, обраний метод і допустимий стан; розрахувати суму/валюту сервером; повторна спроба з idempotency key. Узгодити очікуваний array-return контракт sCheckout із bool-відповідями без окремого security-висновку про них.
- **Приймання й регресії:** змінені cost/products/currency/owner/method у request не впливають на invoice; чужий order і вже завершений платіж не створюють спробу; allowed retry не дублює invoice; provider calls замінені тестовим transport, жодних реальних платежів.

### B15 — ownership і побічні операції payment renderers

- **Історія:** H-033, картка PARTIAL. **Зараз:** у загальному renderer немає власного ownership guard; публічна IDOR поточного checkout не доведена. **Пріоритет: P1 для контракту доступу.**
- **Докази й потік:** `src/sCommerce.php:910–932` приймає ID/identifier/array, знаходить метод і викликає payButton; `src/Payment/BankInvoicePayment.php:155–193` знаходить order та рендерить реквізити/суму. PRO Stripe `111–140,435–457`, WayForPay `49–78` і Monobank `53–87` також завантажують order без owner check; у них renderer може синхронізувати статус через API, створювати/використовувати session або redirect. Portmone callback-in-renderer уже обліковано тільки в B09.
- **Передумови/права:** endpoint/template, який дозволяє користувачу передати ідентифікатор або array до renderer. У пакетних routes окремого payButton route немає. `D35/views/thankyou.blade.php:12` викликає renderer з готовим array, але контроль отримання саме цього order треба перевіряти у відповідному project controller; це інший installed sCommerce commit.
- **Захисти/невідоме:** випадковий order identifier генерується у checkout (`src/Checkout/sCheckout.php:866–868`); він може бути guest capability лише за явної політики. Числовий ID та довільний array не мають такої властивості. Call-site authorization, template overrides і guest policy невідомі.
- **Напрям:** визначити trusted-order contract; авторизація перед renderer, перевірене серверне завантаження, scoped/expiring guest capability; фінансові mutation винести з показу кнопки у явні сервіси.
- **Приймання й регресії:** реальні project endpoints для owner/non-owner/guest із валідною та невалідною capability; чужі order details/links не повертаються; довільний array не обходить server totals; відображення без явної дії не створює платіж або callback mutation. Не оголошувати IDOR лише за відсутністю guard у library method.

### B16 — визначити й застосувати серверні права manager-мутацій

- **Історія:** варіанти H-033, картка PARTIAL; settingsSave винесено виключно в B02. **Зараз:** для частини дій захист обмежений загальним module access; бажана гранулярність прав потребує погодження. **Пріоритет: P1.**
- **Докази й потік:** прямий `get` → `module/sCommerceModule.php:651–675` orderSave/payment status; `2277–2279` currenciesSave; `2398–2418` deliverySave/cost/settings; `2531–2551` paymentSave/credentials/settings/mode. У цих cases немає власного `hasPermission` чи POST-only guard до mutation. Типи integer/float/boolean і prepareSettings/prepareCredentials обмежують форму частини даних, не повноваження.
- **Права/передумови:** manager module access і core CSRF за наявності. Якщо бізнес-політика навмисно дає всім таким manager повний доступ, відсутність окремих permissions сама по собі не доводить порушення цієї політики. Потрібна погоджена матриця ролей/дій, особливо для gateway credentials і ручного зарахування оплати.
- **Наявні захисти:** core exec_module/ModuleAccess; нові bulk/repeat controllers мають локальні guards: `src/Controllers/OrderBulkStatusController.php:17–28`, `OrderBulkMenuController.php:16–25`, `OrderBulkExportController.php:17–25`, `OrderRepeatController.php:29–39`. Ці докази не поширюються на старі cases.
- **Напрям:** спільний action authorization/POST guard; розділення order editing, payment administration, delivery administration і currencies/settings; застосувати однаково до прямих dispatch і UI. SQL/serializer не дублювати тут.
- **Приймання й регресії:** role×action матриця з прямими запитами незалежно від visibility вкладок; denied action не змінює order/credentials/files/events; GET не виконує мутацію; дозволена дія з токеном зберігає backward-compatible результат.

### B17 — reviewSave: allowlist полів замість усіх колонок БД

- **Історія:** варіант H-033, картка PARTIAL. **Зараз:** schema-driven mass assignment підтверджено. **Пріоритет: P2; P1, якщо review manager має обмежені повноваження.**
- **Докази й потік:** `module/sCommerceModule.php:2126–2134` бере request і прибирає a/id, знаходить review; `2194–2201` перетинає всі колонки s_reviews із request keys, присвоює їх через property і save. Це обходить обмеження Eloquent fillable/guarded для масового fill. Schema на `database/migrations/2023_12_27_190234_ecommerce_structure_tables.php:206–220` містить user,parent,product,lang,published,timestamps тощо.
- **Права/передумови:** manager з module access; granular review policy відсутня у наданих вимогах. Ключ id прибрано — не стверджується можливість змінити primary key цим запитом. Майбутні нові колонки автоматично розширюють поверхню assignment.
- **Захисти/невідоме:** intersection із реальними колонками, integer find ID, core access/CSRF; немає явних business validation/field allowlist. Побічні зміни product votes до save review (`2136–2190`) потребують узгодженості з валідацією/транзакцією.
- **Напрям:** validated DTO/allowlist editable fields, окреме керування author/published/system timestamps; перевірка product/parent/lang/rating і права; атомарність review/votes.
- **Приймання й регресії:** user/timestamps/system fields і невідомі keys відхиляються або ігноруються за контрактом; rating/product/parent валідні; нова DB-колонка не стає writable без зміни allowlist; помилка валідації не змінює product votes.

### B18 — параметризувати пошуковий points SELECT товарів

- **Історія:** H-034, CONFIRMED. **Зараз:** raw SQL із request підтверджено, manager input path встановлено; публічна SQL injection поточного deployment не заявляється. **Пріоритет: P1.**
- **Докази й потік:** manager `index.php?a=112&...&get=products&search=...` → `module/sCommerceModule.php:958–960` → `src/Models/sProduct.php:256–285`: stripTags/regex залишає apostrophe та іншу пунктуацію → searchPhrase/word інтерполюються в CASE LIKE raw SELECT. `where/orWhere` на `286–307` використовують звичайні builder values/bindings; вони не захищають окремий raw points вираз. Імена полів fixed і wrap застосовано до identifiers, не до вводу.
- **Додаткова досяжність:** `src/sCommerce.php:90–91`, `src/Controllers/sCommerceController.php:678–680`, `src/Filter/sFilter.php:596` викликають search у storefront listing за умов інтеграції. У D35 plugin (`D35/core/custom/packages/main/plugins/sPlugin.php:5–7`) розпізнає сегмент search; SearchController (`.../src/Controllers/SearchController.php:74–87`) встановлює checkAsSearch і викликає listing. Це конкретний локальний приклад шляху, але він використовує інший installed sCommerce; публічний endpoint із поточним commit не встановлено.
- **Права/передумови:** manager access для доведеного manager-входу або project storefront, що викликає цей scope. DB grammar/sql_mode і результат сформованого SQL не перевірено runtime. Навіть звичайний apostrophe може впливати на синтаксис; довільний SQL-ефект не демонструвався.
- **Напрям:** selectRaw з placeholders/bindings для phrase/кожного слова; ліміти search length/token count; зберегти scoring для focused/all_words/blurred.
- **Приймання й регресії:** апостроф, Unicode, SQL-пунктуація й wildcard трактуються як дані за погодженою search-семантикою; ввід відсутній у SQL-тексті й присутній у bindings; ranking стабільний у всіх режимах; manager і конкретний project search endpoint перевірені окремо.

### B19 — параметризувати пошуковий points SELECT атрибутів

- **Історія:** H-034, CONFIRMED. **Зараз:** окремий raw SELECT із request у scope атрибутів. **Пріоритет: P1.**
- **Докази й потік:** manager `get=attributes&search=...` → `module/sCommerceModule.php:1856–1860` → `src/Models/sAttribute.php:89–107`: слова після regex, що зберігає apostrophe, вставляються у CASE LIKE на `103`, потім DB::Raw на `105`. На відміну від товарів, відкидаються слова довжиною ≤2; це не escaping. Поля сталі, orWhere на `106` параметризує інший фрагмент.
- **Права/передумови:** доведений шлях через manager/module access. Публічний endpoint саме для цього scope в пакетних routes не знайдено. SQL mode/driver та експлуатаційний наслідок невідомі.
- **Захисти:** stripTags/character filter, minimum word length, binding в where, binding мови в scopeLang (`67–77`). Вони не захищають points SELECT.
- **Напрям:** bindings усіх LIKE values у scoring, коректне grammar wrapping identifiers, ліміти вводу; перевірити dialect semantics подвійних лапок для назв колонок.
- **Приймання й регресії:** quoted і довгі Unicode слова не порушують SQL; дані лише в bindings; короткі/порожні пошуки зберігають погоджену поведінку й сортування; тести цього scope окремі від B18.

### B20 — прибрати інтерполяцію мови в CSV raw SQL

- **Історія:** мовний варіант H-035, картка CONFIRMED. Catalog root уже обліковано в B03. **Зараз:** частина мовних SQL-запитів без bindings зберігається. **Пріоритет: P2; підвищити після встановлення недовіреного writer мови.**
- **Докази й потік:** `src/Controllers/sCommerceController.php:215–217` читає `s_lang_default`; `src/Integration/ImportExportCSV.php:1272–1275` вставляє його в FIELD raw. `csvDefaultLanguage():1799–1802` робить лише trim/lowercase; `getProductSeoField():2344–2348` і `syncProductSeo():2371–2384` інтерполюють defaultLang/targetLang. Потрібні CSV execution і, для SEO-гілок, sSeo support. Мовні ключі заголовків CSV обмежено regex на `1867–1888`, тому вони не є довільним SQL-вводом.
- **Права/джерела:** default language надходить із CMS/sLang config, не прямо з `basic__...` request settingsSave. У перевіреному `D35/core/vendor/seiger/slang/src/Controllers/sLangController.php:191–199` setter дозволяє значення зі списку sLang::langList. Повний dispatch прав і походження всіх значень цього списку та інші DB/config writers не встановлені. Не стверджується публічна SQL injection через параметр lang.
- **Захисти:** bound whereIn/where; regex CSV language headers; whitelist у переглянутому setter. Raw orderBy value залишається незв’язаним навіть за наявності цих обмежень.
- **Напрям:** placeholders для кожного FIELD аргументу; єдиний validation contract locale для settings/CSV; перевірити всі writer-и без дублювання B03.
- **Приймання й регресії:** мова з лапками/масивом/неприпустимим кодом відхиляється на write boundary або залишається лише binding; звичайні base/uk/en і fallback SEO працюють; конфіг, CSV headers і SQL order мають окремі fixtures. Визначено, яка роль має право змінювати s_lang_default.

### B21 — checkout: перевірити повторне створення й повторний submit

- **Історія:** варіант C-010 про повторне створення замовлення, картка CONFIRMED; точний старий шлях у витягу не деталізований. Manager-repeat із захистами винесено в R03. **Зараз:** у storefront processOrder окремий ключ ідемпотентності не видно. **Пріоритет: P2 для завершення перевірки.**
- **Докази й потік:** `src/Http/routes.php:24–26,50` → `src/Checkout/sCheckout.php:432–469` → `saveOrder():864–904`, який щоразу генерує новий identifier і створює order; сесію очищено після payment/notifications на `527–528`. Transaction на `897–900` охоплює save/reference, а не request dedup. На `450` delivery cost додається до поточного cost.
- **Передумови/права:** активна checkout-сесія з products; паралельні/повторні запити або збій до session clear. Ефективні session locks і правила повтору на deployment невідомі; послідовний запит після успішного очищення вже не має products. Дублікат у runtime не відтворювався; не змішувати це з контрольованим manager-repeat.
- **Захисти:** products/method checks, випадковий identifier, DB transaction для creation/reference і очищення сесії. Випадковий identifier не є dedup ключем одного submit.
- **Напрям:** одноразовий checkout attempt/idempotency key, серверний повторний розрахунок суми та атомарне створення; retries після provider/mail failure мають повертати попередній результат без повторного платежу.
- **Приймання й регресії:** double-click, concurrent submit, timeout після save та retry після provider error дають один order/payment attempt і не подвоюють delivery cost; нова незалежна покупка працює; notifications/events не множаться. Використати fake gateway/mail, не реальні зовнішні операції.

### B22 — дані доставки: завершити перевірку trust boundary

- **Історія:** варіант H-033, картка PARTIAL; повного старого опису немає. **Зараз:** є валідація й серверний розрахунок, узагальнена вразливість доставки не підтверджена. **Пріоритет: P2, завдання перевірки.**
- **Докази й потік:** browser set-order-data → `src/Checkout/sCheckout.php:74–112,366–409`: правила, зокрема method із registered keys, delivery-specific rules та event override; filtered rules/validated result merge у session; selected method calculateCost на `402–405`. `src/Delivery/CourierDelivery.php:66–79,90–102` валідує поля й бере ціну з stored settings; `src/Checkout/sCheckout.php:450,880–884` додає delivery cost і зберігає order; `views/orderTab.blade.php:87–95` escaped-вивід доставки.
- **Права/передумови:** storefront checkout для вводу адреси; manager/module access для deliverySave settings (`module/sCommerceModule.php:2398–2418`, права — B16). Конкретні сторонні delivery classes, project event rules і протокол історичної претензії не задані.
- **Захисти/невідоме:** validation rules, server calculateCost та escaping на перевіреному manager sink. Не встановлено, чи всі сторонні methods однаково забороняють client cost/system tracking fields і чи зміна method не залишає чужі дані після recursive merge. Це питання для перевірки, а не доказ довільного client-controlled cost.
- **Напрям:** окремий validated DTO для кожного method; очищення stale method data; фінальний перерахунок і повна валідація перед save; system shipment/tracking поля лише від дозволеного writer.
- **Приймання й регресії:** client delivery.cost/system fields не знижують суму й не створюють shipment; зміна method видаляє stale дані; невалідна адреса не зберігається; allowed address/Unicode працюють; HTML у довільній адресі не виконується у manager. Конкретний перелік перевірити з повною H-033, якщо архів стане доступний.

## 5. Історія: відсутні або вже захищені шляхи

### R01 — старі п’ять integration routes та IntegrationActionController

**H-033, історично PARTIAL. Поточний результат: неповністю видалена інтеграція.** `src/Http/routes.php:82–86` усе ще оголошує serverLimits/start/upload/progress/download для IntegrationActionController, але `src/Integration/IntegrationActionController.php` відсутній у filesystem і tracked files; `plugins/sCommercePlugin.php:11` також має залишковий import. Тому твердження «всі п’ять route declarations відсутні» для цього checkout хибне, а твердження «старий controller працює» не підтверджене. За звичайного autoload це не завершений executable path; зовнішній class override не перевірявся.

Рішення: не відновлювати controller через історичну картку. Напрям майбутнього maintenance — прибрати stale declarations/imports або документувати свідомий compatibility adapter. Приймання: актуальний CSV UI працює через sTask, старі URL не резолвляться до зниклого класу, немає class-not-found; окремі права sTask — B06. Це maintenance P2, не додаткова доведена security vulnerability.

### R02 — XSS імені покупця у перевірених manager-шаблонах

**Варіант C-010, картка історично CONFIRMED; точний історичний sink не надано. Поточний результат: на перевірених sink raw-вивід імені не підтвердився.** Checkout user.first_name/middle_name/last_name проходить string/max validation (`src/Checkout/sCheckout.php:79–83`) → `user_info` (`879–880`) → `views/ordersTab.blade.php:336,345`, `views/dashboardTab.blade.php:65`, `views/orderTab.blade.php:10,30,114–124`. Вивід через Blade `{{...}}`; навіть після html_entity_decode у двох шаблонах фінальний output знову escaped. Автор вводу — покупець; manager переглядає order.

Рішення: залишити історичну претензію закритою в межах цих шаблонів, не заявляти аудит усіх project overrides/JS sinks. Регресії: HTML, encoded HTML, лапки й Unicode у customer name показуються як текст у list/dashboard/detail/inputs, без подій DOM. Власне validation без escaping не вважати захистом XSS.

### R03 — повтор замовлення в manager

**Варіант C-010, точний старий підсценарій невідомий. Поточний результат: контрольований шлях із явними захистами.** `module/sCommerceModule.php:540–553` GET відкриває unsaved draft із manager/exec_module guard; `648–649` передає save в `src/Controllers/OrderRepeatController.php:27–46`: POST-only, manager ID, exec_module і CSRF/hash_equals, source ID та draft token; `94–105` allowlist user fields і server-derived identifier із manager/source/token. `src/Services/OrderRepeater.php:25–64` скидає payment/shipment state; `77–115` source row lock, transaction, lookup попереднього identifier, відмова для deleted duplicate і створення одного результату. Schema order identifier unique (`database/migrations/2023_12_27_190234_ecommerce_structure_tables.php:196`).

Рішення: не переносити стару претензію про неконтрольований manager repeat на цей шлях. Ключ обмежує повтор **того самого** draft token; новий токен може свідомо створити новий повтор. Гранулярність manager прав залишається політикою B16. Регресії: GET/cancel без order write; немає прав/CSRF — відмова; однаковий token послідовно/паралельно дає один результат; новий review draft може створити інший; payment/ledger/shipment metadata не копіюються. Runtime-перевірку цього висновку тут не проводили.

### R04 — мовні запити, де bindings уже є

**Пов’язано з H-035, картка історично CONFIRMED.** `module/sCommerceModule.php:1228–1229`, `src/Models/sProduct.php:209–223,381`, `src/Models/sAttribute.php:67–77,152` використовують placeholders для мови. Raw SQL сам по собі не означає unbound value. Ці місця не потрібно виправляти лише за старими номерами рядків; незахищені CSV FIELD залишені в B20. Регресії: у цих запитах locale залишається binding, порядок base/default не змінюється.

### R05 — gateway без callback-реалізації

**Пов’язано з H-033, картка PARTIAL.** Cash/BankInvoice у пакеті не реалізують handleWebhook; `PRO/Payment/MonobankInvoicePayment.php:13` наслідує BasePaymentMethod і також не має власного handleWebhook у переглянутому класі. `src/sCommerce.php:710–711` повертає WEBHOOK NOT IMPLEMENTED/501. Не вимагати підпис у неіснуючому handler і не плутати manual invoice з автоматичним webhook payment. Регресія: callback не змінює order і не трактує відсутність handler як успішну оплату.

### R06 — H-022: назва товару саме у productsTab

**Історичний ID H-022; точний статус у витягу не вказано. Поточний результат: твердження про unescaped назву в цьому шаблоні не підтвердилося.** `views/productsTab.blade.php:180–190` виводить SKU, image attributes і pagetitle через `{{...}}`, зокрема назву всередині `<b>` на `190`. Потік: stored product translation → manager products query (`module/sCommerceModule.php:958–960`) → Blade escaped output. Записати product title може manager або authorized importer; відкриває сторінку manager.

Висновок обмежений **назвою у цьому файлі**. Escaping із ordersTab чи іншого компонента не був доказом H-022. URL scheme safety, JS sinks та project overrides окремо не доведені. Напрям — зберегти escaping; регресії з HTML/encoded HTML/лапками в pagetitle повинні показувати текст, без додаткового blanket XSS-ticket за це місце.

## 6. Окрема пов’язана перевірка manager_language/include

### B23 — перевіряти locale перед package include

- **Історія:** варіант H-033 та ROOT M-038; для M-038 точного статусу немає, для H-033 картка PARTIAL. **Зараз:** session-derived шлях до include без локальної allowlist підтверджено; недовірений writer session value та експлуатація не встановлені. **Пріоритет: P2.**
- **Докази й потік:** `src/sCommerceServiceProvider.php:224–238`: `$_SESSION['mgrUsrConfigSet']['manager_language']` → конкатенація `lang/<value>/global.php` → include. Cache fallback на `232–234` обмежує match `\w+`; session-гілка цієї перевірки не має. Виклик відбувається під час manager module registration, а не через settingsSave request.
- **Джерело/права:** `E36/core/src/Core.php:6911–6931` читає UserSetting і записує manager settings у session. `E36/manager/processors/save_settings.processor.php:6,51–54,93–94` має settings permission і ManagerLanguage exists/resolve; `E36/core/src/Support/ManagerLanguage.php:46–69` обмежує locale name та installed file. Це захищений writer глобальних налаштувань, але не доказ перевірки кожного user-settings writer або кожної session value. Cookie не прирівнюється до можливості довільно редагувати серверну сесію.
- **Наявні захисти/обмеження:** manager context, fixed prefix/suffix, cache regex, core locale helper на інших шляхах. Для виходу за package lang потрібні контроль manager_language і відповідний target із суфіксом `/global.php`; сам include не доводить довільне читання/виконання будь-якого файла.
- **Напрям:** package locale map/allowlist + exists із fallback, незалежно від trusted core writer; canonical containment при filesystem lookup; простежити права всіх writers UserSetting/manager_language у фактичному core.
- **Приймання й регресії:** валідні локалі завантажуються; traversal, absolute/URL-like value, array, відсутня locale не включають зовнішній файл і дають safe fallback; перевірено глобальні settings, персональні settings, session і cache-гілки. Не дублювати цей пункт як окрему M-038-вразливість.

## 7. Порядок майбутньої роботи та невирішені питання

1. **B01 + B02 + B03:** серіалізація, право settingsSave і catalog-root SQL як пов’язаний ланцюг, із незалежними acceptance checks для кожної причини.
2. **B08/B09:** звірити фактичні installed gateway commits і використання Stripe/Portmone; за збігу коду першочергово закрити unsigned callback paths, включно з Portmone renderer.
3. **B04, B06, B18/B19:** CSRF/session matrix конкретного core, worker authorization/containment і parameter binding обох search scopes.
4. **B10–B16:** платіжні attempt/event/state інваріанти й погоджена матриця manager прав; ownership перевіряти на реальних call sites.
5. **B05/B07/B17/B20–B23 та R01:** URL-token compatibility, parser options, DTO/allowlists, language boundaries, checkout retries, delivery contract і stale routes. Історичні захищені шляхи зберегти regression coverage.

Перед реалізацією потрібно з’ясувати:

- Де лежить повний архів 06.11.2026 і які точні незалежні сценарії описані під H-033/C-010; наданий витяг не дозволяє оголосити вичерпність старого аудиту.
- Який сайт і lock/vendor tree є цільовим deployment для commit `9e9d601...`; E36/D35/PRO/TASK — локальні порівняльні джерела, не єдина доведена інсталяція.
- Які effective web/mgr middleware, custom overrides, route cache, session handlers/locks і cookies; чи справді browser endpoints мають серверний CSRF guard для guest/web users.
- Чи окремі sTask routes гарантовано вимагають manager authentication та `stask`/worker permission, хто може змінювати metadata і чи upload має owner binding.
- Чи PHP/worker має доступ на запис/читання потрібних каталогів; чи symlink/canonical boundary і atomic writes забезпечені в deployment.
- Які payment methods/credentials активні, які signature протоколи, account/environment, event identity та state machine підтримані; чи застосовано ledger migration/index і чи gateway ним користується.
- Яка погоджена матриця ролей для settings, currencies, review, ручної оплати, delivery/payment administration; чи order identifier є дозволеною guest capability і за яких меж.
- Які project templates/controllers викликають search/payButton, хто змінює manager_language/s_lang_default і які events змінюють checkout validation.

Цей документ не є підтвердженням безпечного deployment або відтвореної експлуатації. Результат роботи — один новий Markdown-файл; код не змінювався, runtime-тести не виконувалися.
