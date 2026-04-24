# Безопасность x24.sx

Что уже сделано в коде + что нужно нажать в Cloudflare.

## ✅ Сделано в коде (деплоится через git)

### .htaccess
- Отключен листинг папок, MultiViews, CGI
- Скрыт `X-Powered-By`, `Server`, `ServerSignature`
- Заблокирован прямой доступ к `.php` системным (`_init`, `config`, `bootstrap`, `wp-config`)
- Заблокированы расширения данных/конфигов (`.db`, `.sqlite`, `.log`, `.env`, `.yml`, `.md`, `.sql`, `.bak` и др.)
- Заблокированы скрытые файлы (`.git`, `.DS_Store`, `.htaccess` и т.д.)
- Заблокирован доступ к `/data/` (SQLite + пароли)
- Блок probe-запросов: `wp-admin`, `xmlrpc`, `phpmyadmin`, `adminer`, `.env`, и 15+ других путей сканеров
- Блок SQL-injection/XSS/traversal паттернов в URL
- Блок 20+ плохих user-agent'ов (sqlmap, nikto, wpscan, nmap, masscan, acunetix, nessus, …)
- Блок пустых/IP-only Host запросов
- Security headers: HSTS, X-Frame, X-Content, Referrer, Permissions, COOP/CORP
- `LimitRequestBody 5 MB` — анти-POST-flood

### _init.php
- `expose_php=0`, `display_errors=0`, `display_startup_errors=0`, `log_errors=1`
- `session.cookie_httponly=1`, `cookie_secure=1`, `samesite=Lax`, `use_strict_mode=1`
- PDO SQLite с `busy_timeout=3000`
- Миграция v2: индекс `(action, timestamp)` на `rate_limits`, авто-очистка, VACUUM

### Админка
- **Honeypot** скрытое поле `website` — бот заполнит, пользователь не увидит
- **Timestamp** `ts` — если POST пришёл быстрее 1 сек после рендера формы → 403
- **CSRF** токен в форме логина + проверка
- **Rate limit**: 10 попыток входа за 15 мин с IP
- **Прогрессивная задержка**: 0.5s, 1s, 1.5s, … до 5s по числу недавних фейлов
- **Session binding**: IP + User-Agent + таймаут 2 часа. Любое несовпадение → logout
- `session_regenerate_id(true)` при логине и смене пароля
- Одинаковая задержка при неверном пароле (timing-attack resist)
- Пароль: bcrypt cost 12, мин. 10 символов

### health.php
- Защищён секретным токеном, без токена → 404
- Токен генерируется одноразово по `?init=1`

---

## 🟠 Что нажать в Cloudflare (5 минут)

Открой https://dash.cloudflare.com → сайт **x24.sx**.

### 1. SSL/TLS → Overview
- **Encryption mode: Full (strict)** — если у MonoVM есть валидный cert. Если Full не работает — **Full** (не Flexible!). Flexible небезопасен.

### 2. SSL/TLS → Edge Certificates
- ✅ **Always Use HTTPS: ON**
- ✅ **Automatic HTTPS Rewrites: ON**
- ✅ **Minimum TLS Version: TLS 1.2**
- ✅ **Opportunistic Encryption: ON**
- ✅ **TLS 1.3: ON**
- ✅ **HSTS:** Enable с настройками:
  - Max-Age: 6 months
  - Apply HSTS to subdomains: ON
  - Preload: ON
  - No-Sniff Header: ON

### 3. Security → Settings
- **Security Level: Medium** (High если замечаешь атаки)
- **Browser Integrity Check: ON** — бьёт скрипты с плохим UA
- **Challenge Passage: 30 minutes**

### 4. Security → Bots
- **Bot Fight Mode: ON** (бесплатно) — режет известные bot-сети
- Если есть Pro — **Super Bot Fight Mode: ON** с challenges для Definitely Automated

### 5. Security → WAF
- **Managed Rules: ON** (базовые правила включены по умолчанию на Free)
- Добавь свой **Custom Rule**:
  - Name: `block-admin-from-bad-uas`
  - Expression: `(http.request.uri.path contains "/admin") and (not cf.client.bot) and (http.user_agent eq "")`
  - Action: **Block**
- Добавь ещё одно:
  - Name: `rate-limit-admin`
  - Expression: `(http.request.uri.path contains "/admin")`
  - Action: **Managed Challenge**

### 6. Security → Rate Limiting Rules (free tier: 1 rule)
Создай правило:
- Name: `throttle-post`
- If: `(http.request.method eq "POST")`
- Characteristics: IP
- Rate: **10 requests per 10 seconds**
- Action: **Block** for 1 minute

### 7. Rules → Page Rules (free tier: 3 rules)
Правило 1 — кэшировать статику:
- URL: `*x24.sx/*.css`
- Settings: `Cache Level = Cache Everything`, `Edge Cache TTL = 1 month`

Правило 2 — жестко защитить админку:
- URL: `*x24.sx/admin/*`
- Settings: `Security Level = High`, `Cache Level = Bypass`, `Disable Performance`

Правило 3 — не кэшировать health:
- URL: `*x24.sx/health.php*`
- Settings: `Cache Level = Bypass`

### 8. DNS
- A-запись `x24.sx` → `45.87.212.246` → **Proxy ON (оранжевое облако)** — без этого вся защита CF отваливается
- Удалить все неиспользуемые CNAME/TXT
- Добавить **CAA**: `0 issue "letsencrypt.org"` + `0 issue "cloudflare.com"` — запрещает выпуск сертов другими CA
- Добавить **SPF**: `TXT @ "v=spf1 -all"` — если не шлёшь почту, это блокирует спуфинг

### 9. Analytics → Firewall Events
- Раз в неделю зайди сюда — увидишь кого заблокировали. Полезно.

---

## 🔴 Что НЕ делать
- ❌ **Не переключать NS на MonoVM** (ns3.monovm.host) — это обнажит origin IP и убьёт всю защиту CF
- ❌ **Не включать SSL mode = Flexible** — это MITM-atтака ready, CF→origin идёт по HTTP
- ❌ **Не хранить секреты/пароли/токены в git** — используй `data/` или переменные окружения cPanel
- ❌ **Не показывать `display_errors`** — в проде никогда
- ❌ **Не расшаривать ссылку на `/health.php?t=TOKEN`** — токен как пароль, никому

---

## 🛡 Против DDoS конкретно
1. **CF proxy ON** — самое важное. CF абсорбирует L3/L4 атаки бесплатно
2. **Bot Fight Mode ON** — чистит bot-сети до того как они попадут на origin
3. **Rate Limiting Rule** (настроен выше) — режет по IP на CF edge, до сервера
4. **LimitRequestBody 5M** в .htaccess — режет body-based amplification
5. **Под атакой:** в CF → Security → Settings → **Under Attack Mode: ON**. Все посетители проходят JS-challenge, боты отсекаются 99%. Выключить когда атака прошла.

---

## 🔑 Что периодически делать
- **Раз в месяц:** зайти в Security → Firewall Events, глянуть что блокировали
- **Раз в 3 месяца:** менять пароль админки
- **Раз в год:** менять домен-токен `/health.php` (удалить `/data/health_token.txt` → перегенерить)
- **После подозрительной активности:** `session_destroy` всех (просто удалить все сессии в cPanel → PHP Session Manager или просто `rm -rf tmp/sess_*` через File Manager)
