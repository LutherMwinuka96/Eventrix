# KIUT-Eventrix Campus

**Mfumo wa Usimamizi wa Matukio ya Chuo** — Campus Event Management System

Eventrix ni mfumo wa kisasa wa kusimamia matukio ya chuo kikuu. Unaruhusu **wanafunzi**, **waandaaji (organizers)**, na **wasimamizi (admins)** kutengeneza, kuidhinisha, kusajili, na kufuatilia matukio ya kampasi kupitia kivinjari.

> **Credentials:** Angalia [`CREDENTIALS.md`](CREDENTIALS.md) kwa akaunti za mfano, URLs, na muundo wa reg number.

---

## Yaliyomo

1. [Muhtasari](#muhtasari)
2. [Vipengele](#vipengele)
3. [Teknolojia](#teknolojia)
4. [Mahitaji](#mahitaji)
5. [Usanidi (Setup)](#usanidi-setup)
6. [Kuingia kwenye Mfumo](#kuingia-kwenye-mfumo)
7. [Muundo wa Mradi](#muundo-wa-mradi)
8. [Majukumu ya Watumiaji](#majukumu-ya-watumiaji)
9. [API Endpoints](#api-endpoints)
10. [Kufanya Kazi Offline](#kufanya-kazi-offline)
11. [Matatizo ya Kawaida](#matatizo-ya-kawaida)

---

## Muhtasari

Eventrix inachanganya:

- **Landing page** — inaonyesha matukio yaliyoidhinishwa kutoka database
- **Programu kuu** (`pages/app.html`) — dashboard kamili na menyu kwa kila jukumu
- **Backend ya PHP + MySQL** — auth, events, approvals, notifications, users
- **Rasilimali za ndani** — fonts, icons, charts, Excel parser — **hakuna CDN/internet inayohitajika**

> **Muhimu:** Endesha mradi kupitia **WAMP/XAMPP/Apache**. Usifungue HTML kwa `file://` — PHP na API hazitafanya kazi.

---

## Vipengele

### Wanafunzi (Student)
- Kuingia kwa **nambari ya usajili pekee** (hakuna nenosiri, hakuna self-registration)
- Lazima kuwa kwenye database (admin huwasajili)
- Kutazama matukio yaliyochapishwa, kusajili/kujiondoa
- Kalenda, arifa, na usimamizi wa usajili

### Waandaaji (Organizer)
- Self-signup kwenye `pages/signup.html` — **inahitaji idhini ya admin**
- Kuunda na kuhariri matukio, pakia poster
- Matukio ya bure au kulipiwa
- Kufuatilia idhini ya admin kwa matukio mapya

### Wasimamizi (Admin)
- **Event Approvals** — idhinisha, kataa, bulk approve/reject
- **User Management** — ongeza/futa watumiaji, pending organizers
- **Bulk student registration** — range au Excel/CSV upload
- **Reports & Analytics** — grafu kutoka data halisi (Chart.js)
- Arifa za mfumo na uchapishaji wa matukio moja kwa moja

---

## Teknolojia

| Sehemu | Teknolojia |
|--------|------------|
| Frontend | HTML5, CSS3, JavaScript (Vanilla) |
| Charts | Chart.js — `assets/js/chart.umd.min.js` |
| Excel | SheetJS (XLSX) — `assets/js/xlsx.full.min.js` |
| Icons | Font Awesome 6 — `assets/vendor/fontawesome/` |
| Fonts | Inter, Poppins, Plus Jakarta Sans, Urbanist — `assets/fonts/` |
| Backend | PHP 8+ |
| Database | MySQL / MariaDB |
| Server | Apache (WAMP / XAMPP) |
| Auth | Session-based + role-based access |

---

## Mahitaji

- **WAMP64**, **XAMPP**, au Apache + PHP + MySQL
- PHP 8.0+
- MySQL 5.7+ au MariaDB 10+
- Kivinjari kisasa (Chrome, Edge, Firefox)
- **Hakuna internet inayohitajika** baada ya usanidi (WAMP + MySQL tu)

---

## Usanidi (Setup)

### Hatua 1 — Weka faili za mradi

```text
C:\wamp64\www\Eventrix\
```

Au kwa XAMPP:

```text
C:\xampp\htdocs\Eventrix\
```

### Hatua 2 — Anzisha huduma

1. Fungua **WAMP** au **XAMPP Control Panel**
2. Washa **Apache**
3. Washa **MySQL**

### Hatua 3 — Database

1. Fungua **phpMyAdmin**: `http://localhost/phpmyadmin`
2. Import faili `database/eventrix.sql`

Jedwali kuu: `users`, `events`, `event_registrations`, `notifications`

### Hatua 4 — Sanidi database

Hariri `config/database.php`:

```php
const DB_HOST = '127.0.0.1';
const DB_NAME = 'eventrix';
const DB_USER = 'root';
const DB_PASS = '';   // weka nenosiri lako la MySQL
```

### Hatua 5 — Ruhusa za upload

Hakikisha folda hii inaweza kuandikwa na Apache:

```text
uploads/events/
```

### Hatua 6 — Jaribu mfumo

| URL | Maelezo |
|-----|---------|
| `http://localhost/Eventrix/` | Landing page |
| `http://localhost/Eventrix/pages/signin.html` | Kuingia |
| `http://localhost/Eventrix/pages/app.html` | Programu kuu |
| `http://localhost/Eventrix/api/health.php` | Ukaguzi wa backend & DB |

Ikiwa `health.php` inarudisha `"success": true`, backend iko tayari.

---

## Kuingia kwenye Mfumo

Angalia [`CREDENTIALS.md`](CREDENTIALS.md) kwa maelezo kamili.

| Jukumu | Jinsi ya kuingia |
|--------|------------------|
| **Admin** | `admin@campus.edu` / `123456` — tab Staff → Admin |
| **Organizer** | `organizer@campus.edu` / `123456` — tab Staff → Organizer |
| **Student** | Reg number: `BCS/23456/2301/DT` — tab Student (hakuna password) |

### Muundo wa Reg Number

```text
COURSE/NUMBER/YEAR/DT
```

Mfano: `BCS/23456/2301/DT`, `BIT/24501/2401/DT`

### Bulk Upload ya Wanafunzi (Admin)

1. Ingia kama Admin → **Users**
2. **Admin User Tools** → **Upload Excel / CSV**
3. Safu zinazohitajika: `reg_no`, `name`, `email`, `faculty`, `department`
4. Preview → **Register Uploaded Students**

Tengeneza faili za mfano:

```bash
python samples/student-upload/generate_samples.py
```

### Mzunguko wa Idhini ya Tukio

```text
Organizer anaunda tukio → pending → Admin (Approvals) → approved / rejected
```

### Mzunguko wa Organizer Mpya

```text
Self signup → pending (inactive) → Admin approve → organizer anaweza kuingia
```

---

## Muundo wa Mradi

```text
Eventrix/
├── index.html                      # Landing page
├── README.md                       # Nyaraka hii
├── CREDENTIALS.md                  # Akaunti na access
├── api/
│   ├── _bootstrap.php              # DB, auth, helpers, reg validation
│   ├── auth.php                    # Login, register, student_access
│   ├── events.php                  # CRUD events, register/unregister
│   ├── approvals.php               # Approve / reject events
│   ├── users.php                   # Users, bulkstudents, organizer approval
│   ├── notifications.php           # Arifa
│   └── health.php                  # Health check
├── assets/
│   ├── css/
│   │   ├── fonts.css               # Fonts za ndani (offline)
│   │   └── auth.css                # Stili za sign in/up
│   ├── fonts/                      # WOFF2 font files
│   ├── js/
│   │   ├── chart.umd.min.js        # Chart.js
│   │   ├── xlsx.full.min.js        # Excel parser
│   │   └── student-reg.js          # Reg validation & bulk upload
│   └── vendor/fontawesome/         # Font Awesome (offline)
├── config/
│   └── database.php                # MySQL credentials
├── database/
│   └── eventrix.sql                # Schema + seed data
├── pages/
│   ├── app.html                    # Programu kuu
│   ├── signin.html, signup.html
│   ├── about.html, faq.html, contact.html, support.html
│   ├── terms.html, privacy.html
│   └── site-pages.css, site-pages.js
├── samples/student-upload/         # Excel samples + generator script
├── scripts/download_fonts.py       # (Dev) kusasisha fonts
└── uploads/events/                 # Poster za matukio
```

---

## Majukumu ya Watumiaji

| Ukurasa | Student | Organizer | Admin |
|---------|:-------:|:---------:|:-----:|
| Dashboard | ✅ | ✅ | ✅ |
| Events | ✅ | ✅ | ✅ |
| Calendar | ✅ | ✅ | ✅ |
| My Registrations | ✅ | — | — |
| My Events | — | ✅ | — |
| Approvals | — | — | ✅ |
| Users | — | — | ✅ |
| Reports | — | — | ✅ |
| Notifications | ✅ | ✅ | ✅ |
| Settings | ✅ | ✅ | ✅ |

---

## API Endpoints

Msingi: `http://localhost/Eventrix/api/`

### `auth.php`

| Action | Method | Maelezo |
|--------|--------|---------|
| `login` | POST | Ingia (email + password + role) |
| `register` | POST | Organizer signup (pending approval) |
| `student_access` | POST | Mwanafunzi kwa reg number |
| `me` | GET | Mtumiaji aliyeingia |
| `logout` | POST | Toka |

### `events.php`

| Action | Method | Maelezo |
|--------|--------|---------|
| `getpublic` | GET | Matukio yaliyoidhinishwa |
| `getall` | GET | Matukio yote (auth) |
| `create` | POST | Tengeneza tukio |
| `update` | POST/PUT | Sasisha tukio |
| `delete` | POST/DELETE | Futa tukio |
| `register` | POST | Jisajili kwenye tukio |
| `unregister` | POST | Jiondoe |

### `approvals.php` (Admin)

| Action | Method | Maelezo |
|--------|--------|---------|
| `getpending` | GET | Matukio yanayosubiri |
| `approve` / `reject` | POST | Idhinisha / kataa |
| `bulkapprove` / `bulkreject` | POST | Vitendo vya pamoja |

### `users.php` (Admin)

| Action | Method | Maelezo |
|--------|--------|---------|
| `getall` | GET | Orodha ya watumiaji |
| `create` | POST | Ongeza mtumiaji |
| `update` | POST/PUT | Sasisha mtumiaji |
| `delete` | POST/DELETE | Futa mtumiaji |
| `togglestatus` | POST | Wezesha/zima akaunti |
| `bulkstudents` | POST | Sajili wanafunzi (range / list) |
| `approveorganizer` | POST | Idhinisha organizer |
| `rejectorganizer` | POST | Kataa organizer |

### `notifications.php`

| Action | Method | Maelezo |
|--------|--------|---------|
| `getall` | GET | Pata arifa |
| `create` | POST | Tengeneza arifa |
| `markread` | POST | Weka kama imesomwa |
| `delete` | POST/DELETE | Futa arifa |

### `health.php`

| Method | Maelezo |
|--------|---------|
| GET | Ukaguzi wa PHP + MySQL |

---

## Kufanya Kazi Offline

Mfumo unafanya kazi **bila internet** ikiwa:

1. **WAMP + MySQL** zinaendesha
2. Unafungua kupitia `http://localhost/Eventrix/` (si `file://`)

Rasilimali zote ziko ndani ya mradi:

| Kitu | Mahali |
|------|--------|
| Fonts | `assets/fonts/` + `assets/css/fonts.css` |
| Icons | `assets/vendor/fontawesome/` |
| Charts | `assets/js/chart.umd.min.js` |
| Excel | `assets/js/xlsx.full.min.js` |

Hakuna CDN, Google Fonts, au jsdelivr inayohitajika wakati wa matumizi.

---

## Matatizo ya Kawaida

### Excel upload inashindwa
- Fanya **Ctrl + F5** kuonyesha upya cache
- Hakikisha `assets/js/xlsx.full.min.js` ipo
- Jaribu CSV kama mbadala

### Grafu za Reports hazionekani
- Hakikisha `assets/js/chart.umd.min.js` ipo
- Fanya hard refresh: **Ctrl + F5**

### Mwanafunzi hawezi kuingia
- Reg number lazima iwe kwenye database
- Muundo sahihi: `BCS/23456/2301/DT`
- Admin lazima asajili mwanafunzi kwanza (bulk au range)

### Organizer hawezi kuingia baada ya signup
- Akaunti inasubiri idhini ya admin
- Admin → Users → **Pending Organizer Approvals** → Approve

### API inarudisha error
- Angalia `api/health.php`
- Thibitisha MySQL inaendesha
- Angalia `config/database.php`

### Live Server haifanyi kazi
- Tumia **WAMP/XAMPP** — PHP inahitaji Apache

---

## Mapungufu

- Malipo halisi (payment gateway) hayajatekelezwa
- Usitumie nenosiri za mfano (`123456`) kwenye production
- `pages/start.html` ina maelekezo ya zamani (CampusFlow) — tumia URLs za Eventrix

---

## Wasilisho / Demo

| Kipengele | Thamani |
|-----------|---------|
| Jina | KIUT-Eventrix Campus |
| Aina | Campus Event Management System |
| URL | `http://localhost/Eventrix/` |
| Credentials | [`CREDENTIALS.md`](CREDENTIALS.md) |

---

## Leseni

Mradi huu ni wa kielimu / demo. Ongeza leseni rasmi ikiwa utausambaza hadharani.

---

*Last updated: June 2026*
