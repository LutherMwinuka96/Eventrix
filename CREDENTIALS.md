# Eventrix Campus — Credentials & Access

> **Onyo la usalama:** Faili hii ina akaunti za mfano (demo) tu. Usitumie nenosiri hizi kwenye mazingira ya uzalishaji (production). Nenosiri halisi la MySQL liweke kwenye `config/database.php` pekee — usilisambaze hadharani.

---

## URLs za Mfumo

| Ukurasa | URL |
|---------|-----|
| Landing page | `http://localhost/Eventrix/` |
| Sign In | `http://localhost/Eventrix/pages/signin.html` |
| Sign Up (Organizer) | `http://localhost/Eventrix/pages/signup.html` |
| Programu kuu | `http://localhost/Eventrix/pages/app.html` |
| Health check | `http://localhost/Eventrix/api/health.php` |
| Backend test | `http://localhost/Eventrix/pages/backend-test.html` |

---

## Database (MySQL)

Sanidi kwenye `config/database.php`:

| Kipengele | Thamani ya chaguo-msingi |
|-----------|--------------------------|
| Host | `127.0.0.1` |
| Database | `eventrix` |
| User | `root` |
| Password | *(weka nenosiri lako la WAMP/MySQL)* |
| Charset | `utf8mb4` |

**Import database:**

```text
database/eventrix.sql
```

---

## Akaunti za Mfano (Demo)

Baada ya kuimport `eventrix.sql`, tumia akaunti hizi:

### Admin

| Kipengele | Thamani |
|-----------|---------|
| Jukumu | Admin |
| Barua pepe | `admin@campus.edu` |
| Nenosiri | `123456` |
| Ukurasa wa kuingia | Sign In → tab **Staff** → chagua **Admin** |
| Maelezo | Idhini ya matukio, watumiaji, ripoti, bulk upload ya wanafunzi |

> Admin lazima aingize **barua pepe na nenosiri** wote — hakuna auto-fill.

---

### Organizer (tayari imeidhinishwa)

| Kipengele | Thamani |
|-----------|---------|
| Jukumu | Organizer |
| Barua pepe | `organizer@campus.edu` |
| Nenosiri | `123456` |
| Idara | Student Affairs |
| Hali | Active (`is_active = 1`) |
| Maelezo | Kuunda matukio, kusubiri idhini ya admin kwa matukio mapya |

---

### Mwanafunzi (Student)

| Kipengele | Thamani |
|-----------|---------|
| Jukumu | Student |
| Nambari ya usajili | `BCS/23456/2301/DT` |
| Nenosiri | *(haitumiki — reg number pekee)* |
| Jina | Student User |
| Barua pepe (kwenye DB) | `student@campus.edu` |
| Idara | Computer Science |
| Maelezo | Wanafunzi **hawajisajili wenyewe** — lazima wawe kwenye database |

**Muundo wa reg number:**

```text
COURSE/NUMBER/YEAR/DT
```

Mfano: `BCS/23456/2301/DT`, `BIT/24501/2401/DT`

| Sehemu | Maelezo | Mfano |
|--------|---------|-------|
| COURSE | Msimbo wa kozi | `BCS`, `BIT`, `BBA`, `LLB`, … |
| NUMBER | Nambari ya mwanafunzi (tarakimu 5) | `23456` |
| YEAR | Mwaka wa kuingia | `2301`, `2401`, `2501`, `2601` |
| DT | Kiambishi cha mwisho | `DT` |

---

## Organizer Mpya (Self Sign-Up)

| Hatua | Maelezo |
|-------|---------|
| 1 | Jisajili kwenye `pages/signup.html` |
| 2 | Akaunti huundwa na `is_active = 0` (pending) |
| 3 | Admin anaona kwenye **Users → Pending Organizer Approvals** |
| 4 | Baada ya **Approve**, organizer anaweza kuingia |

Au admin anaweza kuongeza organizer moja kwa moja (akaunti active mara moja) kupitia **Admin User Tools → Add Organizer**.

---

## Bulk Registration ya Wanafunzi (Admin)

Admin anaweza kusajili wanafunzi kwa:

1. **Range** — kozi + nambari za kuanzia/mwisho + mwaka wa kuingia
2. **Excel / CSV** — safu: `reg_no`, `name`, `email`, `faculty`, `department`

Faili za mfano (tengeneza kwa `samples/student-upload/generate_samples.py`):

```text
samples/student-upload/BCS_students_2301.xlsx
samples/student-upload/BIT_students_2401.xlsx
```

---

## Muhtasari wa Kuingia

| Jukumu | Nini unahitaji | Mahali pa kuingia |
|--------|----------------|-------------------|
| **Student** | Reg number tu | Sign In → tab Student |
| **Organizer** | Email + password | Sign In → tab Staff → Organizer |
| **Admin** | Email + password | Sign In → tab Staff → Admin |

---

## Hash ya Nenosiri (Dev)

Nenosiri `123456` kwenye database imehifadhiwa kama SHA-256:

```text
8d969eef6ecad3c29a3a629280e686cf0c3f5d5a86aff3ca12020c923adc6c92
```

---

## SQL ya Kusasisha Mwanafunzi wa Mfano

Ikiwa reg number ya mwanafunzi wa demo bado ni ya zamani:

```sql
UPDATE users
SET reg_no = 'BCS/23456/2301/DT', department = 'Computer Science'
WHERE email = 'student@campus.edu' AND role = 'student';
```

---

## Mabadiliko ya Nenosiri (Production)

Kabla ya kutumia mfumo kwenye chuo halisi:

1. Badilisha nenosiri za `admin@campus.edu` na `organizer@campus.edu`
2. Sasisha `config/database.php` na nenosiri salama la MySQL
3. Futa au usisambaze faili hii (`CREDENTIALS.md`) hadharani

---

*Last updated: June 2026*
