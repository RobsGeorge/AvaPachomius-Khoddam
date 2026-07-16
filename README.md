<p align="center">
  <img src="https://img.icons8.com/?size=120&id=102454&format=png&color=7C3AED" alt="AvaPachomius Khoddam" width="96" />
</p>

<h1 align="center">AvaPachomius · Khoddam Portal</h1>

<p align="center">
  <strong>Servants preparation platform</strong> for St. Pachomius Church<br/>
  <em dir="rtl">منصة إعداد الخدام — كنيسة القديس أنبا باخوميوس</em>
</p>

<p align="center">
  <a href="https://avapakhomios.com"><img src="https://img.shields.io/badge/Live-avapakhomios.com-7C3AED?style=for-the-badge&logo=google-chrome&logoColor=white" alt="Live site" /></a>
  <a href="#-english"><img src="https://img.shields.io/badge/EN-English-0F2744?style=for-the-badge" alt="English" /></a>
  <a href="#-العربية"><img src="https://img.shields.io/badge/AR-العربية-D4AF37?style=for-the-badge" alt="العربية" /></a>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-10-FF2D20?style=flat-square&logo=laravel&logoColor=white" alt="Laravel 10" />
  <img src="https://img.shields.io/badge/PHP-8.2-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.2" />
  <img src="https://img.shields.io/badge/MySQL-8-4479A1?style=flat-square&logo=mysql&logoColor=white" alt="MySQL 8" />
  <img src="https://img.shields.io/badge/UI-RTL%20first-059669?style=flat-square" alt="RTL first" />
  <img src="https://img.shields.io/badge/i18n-ar%20%2B%20en-6D28D9?style=flat-square" alt="i18n" />
  <img src="https://img.shields.io/badge/Theme-Light%20%2F%20Dark-334155?style=flat-square" alt="Themes" />
</p>

---

## 🇬🇧 English

### Overview

**AvaPachomius Khoddam** is the live church portal used by St. Pachomius Church servants for preparation courses, attendance, academic work, events, and administration.

It is a **production Laravel application** (public site: [avapakhomios.com](https://avapakhomios.com)) evolving into a multi-tenant church platform (**Khedma**) via an expand–contract migration — without breaking today’s single-church deployment.

| | |
|:--|:--|
| 🏛️ **Institute** | St. Pachomius Church |
| 🎓 **Focus** | Servants preparation (Khoddam) |
| 🌐 **Languages** | Arabic (primary, RTL-first) · English |
| 🎨 **Themes** | Soft purple light · Navy & gold dark |
| 🔐 **Access** | OTP registration · Policy + permission RBAC |

### What the portal does

<table>
<tr>
<td width="50%">

#### 🧑‍🎓 Learning & courses
- Course applications & review workflows  
- Curriculum, modules, and sessions  
- Assignments, exams, live quizzes & feedback  
- Grades, graduation criteria, and announcements  

</td>
<td width="50%">

#### ✅ Attendance & people
- Session attendance & personal history  
- Student / servant rosters  
- Profile photos with admin review  
- Guided registration & onboarding  

</td>
</tr>
<tr>
<td width="50%">

#### 🗓️ Events
- Institution & course-scoped events  
- Capacity, eligibility, and reservations  
- Event admin tools & audit trails  

</td>
<td width="50%">

#### 🛡️ Roles & security
- Unified **Roles Hub** (service / course / system)  
- Permission keys via Policies (not role-name checks)  
- Impersonation & role-preview for superadmins  
- Audit logs, force logout, notifications (portal / email / WhatsApp)  

</td>
</tr>
</table>

> **Service layer (in progress):** year-agnostic **Services** own membership & org RBAC; **Courses** own attendance, grades, exams, and graduation. Course enrollment requires service membership.

### Architecture at a glance

```text
┌─────────────────────────────────────────────────────────────┐
│  Web UI (Blade · Bootstrap · Alpine · Khoddam theme)        │
│  Arabic RTL · English LTR · Light / Dark                     │
├─────────────────────────────────────────────────────────────┤
│  Laravel 10 · Policies · Permission keys · Roles Hub         │
│  Notifications · Reverb (realtime) · Queues / Mail           │
├─────────────────────────────────────────────────────────────┤
│  MySQL 8 · Additive migrations · Expand–contract tenancy    │
└─────────────────────────────────────────────────────────────┘
```

| Layer | Stack |
|:------|:------|
| 🖥️ Frontend | Blade, Bootstrap 5, Alpine.js, SweetAlert2, Animate.css — **no npm build step** |
| ⚙️ Backend | PHP **8.2**, Laravel 10, Sanctum, DomPDF, Intervention Image, QR codes |
| 🗄️ Data | MySQL 8 |
| 📡 Realtime | Laravel Reverb |
| ✅ Tests | PHPUnit — Feature / Unit / Smoke / Api / Notifications / Mail / Tenancy |

### Design language

| Theme | Mood |
|:------|:-----|
| ☀️ **Light** | Soft purple gradients · indigo titles |
| 🌙 **Dark** | Navy gradient · gold accents · high-contrast text |

Motion and surfaces are defined in `public/css/khoddam-theme.css` and `public/js/khoddam-ui.js`.
Shared design tokens for web + the Expo mobile app live in `resources/design-tokens/khoddam.tokens.json`
(see `docs/mobile/mvp.md`).

### Local development

**Requirements:** PHP 8.2 · Composer · MySQL 8 · extensions: `pdo_mysql`, `mbstring`, `curl`, `gd`, `xml`, `dom`

```bash
git clone https://github.com/RobsGeorge/AvaPachomius-Khoddam.git
cd AvaPachomius-Khoddam

composer install
cp .env.example .env
php artisan key:generate

# Configure DB_* in .env, then:
php artisan migrate
php artisan serve
```

Run tests:

```bash
php artisan test
# or a suite: php artisan test --testsuite=Feature
```

> **Note:** Keep the CLI on **PHP 8.2** (the project may sit on hosts that also have newer PHP installs).

### Project guidelines (high level)

1. 🔒 Don’t break production while `MULTI_TENANT=false`  
2. ➕ Schema changes are **additive** only (expand–contract)  
3. 🧩 Authorize with **Policies + permission keys**, not role-name strings  
4. 🌍 Localize every new string (**ar** + **en**); Arabic UI first  
5. 📝 Destructive actions write to **audit_log**  
6. ✅ Ship tests with behavioral changes  

Out-of-scope ideas go in [`PARKING-LOT.md`](./PARKING-LOT.md).

### Repository

| | |
|:--|:--|
| 📦 GitHub | [RobsGeorge/AvaPachomius-Khoddam](https://github.com/RobsGeorge/AvaPachomius-Khoddam) |
| 🌍 Production | [avapakhomios.com](https://avapakhomios.com) |

---

## 🇪🇬 العربية

### نظرة عامة

**بوابة أنبا باخوميوس للخدام** هي المنصة الفعلية المستخدمة في كنيسة القديس أنبا باخوميوس لإعداد الخدام: الدورات، الحضور، العمل الأكاديمي، الفعاليات، والإدارة.

التطبيق مبني على **Laravel** ويعمل حاليًا في الإنتاج على [avapakhomios.com](https://avapakhomios.com)، ويجري تطويره تدريجيًا إلى منصة كنائس متعددة المستأجرين (**خدمة / Khedma**) بأسلوب *expand–contract* دون كسر النشر الحالي لكنيسة واحدة.

| | |
|:--|:--|
| 🏛️ **المؤسسة** | كنيسة القديس أنبا باخوميوس |
| 🎓 **التركيز** | إعداد الخدام |
| 🌐 **اللغات** | العربية (أساسية، واجهة من اليمين لليسار) · الإنجليزية |
| 🎨 **السمات** | فاتح بنفسجي ناعم · داكن كحلي وذهبي |
| 🔐 **الوصول** | تسجيل برمز OTP · صلاحيات عبر السياسات والمفاتيح |

### ماذا تقدّم البوابة؟

<table>
<tr>
<td width="50%" dir="rtl">

#### 🧑‍🎓 التعلم والدورات
- طلبات الالتحاق ومراجعتها  
- المنهج والوحدات والجلسات  
- الواجبات والامتحانات والاختبارات المباشرة والتغذية الراجعة  
- الدرجات ومعايير التخرج والإعلانات  

</td>
<td width="50%" dir="rtl">

#### ✅ الحضور والأشخاص
- حضور الجلسات وسجل شخصي  
- كشوف الطلاب / الخدام  
- صور الملف الشخصي بمراجعة الإدارة  
- تسجيل وإرشاد للانضمام  

</td>
</tr>
<tr>
<td width="50%" dir="rtl">

#### 🗓️ الفعاليات
- فعاليات على مستوى المؤسسة أو الدورة  
- السعة والأهلية والحجوزات  
- أدوات إدارة الفعاليات وسجلات التدقيق  

</td>
<td width="50%" dir="rtl">

#### 🛡️ الأدوار والأمان
- **مركز الأدوار** الموحّد (خدمة / دورة / نظام)  
- صلاحيات عبر السياسات والمفاتيح (بدون مقارنة أسماء أدوار)  
- معاينة كمستخدم أو كدور للمشرف العام  
- سجلات تدقيق، إنهاء جلسات، إشعارات (بوابة / بريد / واتساب)  

</td>
</tr>
</table>

> **طبقة الخدمة (قيد التطوير):** **الخدمة** تدير العضوية وصلاحيات التنظيم؛ **الدورة** تدير الحضور والدرجات والامتحانات والتخرج. الالتحاق بالدورة يشترط عضوية الخدمة.

### لمحة معمارية

```text
┌─────────────────────────────────────────────────────────────┐
│  واجهة الويب (Blade · Bootstrap · Alpine · سمة خدام)        │
│  عربي RTL · إنجليزي LTR · فاتح / داكن                       │
├─────────────────────────────────────────────────────────────┤
│  Laravel 10 · السياسات · مفاتيح الصلاحيات · مركز الأدوار    │
│  الإشعارات · Reverb · الطوابير / البريد                     │
├─────────────────────────────────────────────────────────────┤
│  MySQL 8 · ترحيلات إضافية · Tenancy بأسلوب expand–contract  │
└─────────────────────────────────────────────────────────────┘
```

| الطبقة | التقنيات |
|:-------|:---------|
| 🖥️ الواجهة | Blade، Bootstrap 5، Alpine.js، SweetAlert2 — **بدون خطوة بناء npm** |
| ⚙️ الخادم | PHP **8.2**، Laravel 10، Sanctum، DomPDF، Intervention Image، رموز QR |
| 🗄️ البيانات | MySQL 8 |
| 📡 فوري | Laravel Reverb |
| ✅ الاختبارات | PHPUnit — Feature / Unit / Smoke وغيرها |

### لغة التصميم

| السمة | الطابع |
|:------|:-------|
| ☀️ **فاتح** | تدرجات بنفسجية ناعمة · عناوين نيلي |
| 🌙 **داكن** | خلفية كحلية · لمسات ذهبية · نص واضح |

### التشغيل محليًا

**المتطلبات:** PHP 8.2 · Composer · MySQL 8 · الامتدادات: `pdo_mysql`, `mbstring`, `curl`, `gd`, `xml`, `dom`

```bash
git clone https://github.com/RobsGeorge/AvaPachomius-Khoddam.git
cd AvaPachomius-Khoddam

composer install
cp .env.example .env
php artisan key:generate

# اضبط إعدادات قاعدة البيانات في .env ثم:
php artisan migrate
php artisan serve
```

تشغيل الاختبارات:

```bash
php artisan test
```

> **ملاحظة:** ثبّت سطر الأوامر على **PHP 8.2**.

### مبادئ المشروع (باختصار)

1. 🔒 لا تكسر الإنتاج طالما `MULTI_TENANT=false`  
2. ➕ تغييرات المخطط **إضافة فقط**  
3. 🧩 التفويض عبر **السياسات ومفاتيح الصلاحيات**  
4. 🌍 كل نص جديد مترجم (**ع** + **en**)، والواجهة العربية أولاً  
5. 📝 كل إجراء هدّام يُسجَّل في **audit_log**  
6. ✅ الاختبارات مطلوبة مع التغييرات السلوكية  

الأفكار خارج المرحلة الحالية تُضاف إلى [`PARKING-LOT.md`](./PARKING-LOT.md).

---

## 📄 License

This application is private church software unless otherwise stated by the repository owner.  
هذه البرمجية مخصّصة لاستخدام الكنيسة ما لم يُعلن مالك المستودع خلاف ذلك.

---

<p align="center">
  <sub>
    Built with ❤️ for St. Pachomius Church servants · صُنع بمحبة لخدام كنيسة أنبا باخوميوس
  </sub>
</p>
