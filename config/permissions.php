<?php

/**
 * Permission registry — single source of truth for dynamic RBAC.
 * Run `php artisan permissions:sync` after changes.
 */
return [
    'course_access' => [
        'scope' => 'course',
        'label_en' => 'Course Access',
        'label_ar' => 'الوصول للدورة',
        'sort' => 10,
        'permissions' => [
            'course.access' => [
                'label_en' => 'Course enrollment access',
                'label_ar' => 'الوصول للدورة المسجل بها',
                'type' => 'both',
                'routes' => ['curriculum.show', 'curriculum.admin', 'grades.show', 'grades.admin', 'grades.report'],
                'nav' => ['academic.curriculum'],
            ],
            'course.view' => [
                'label_en' => 'View available courses',
                'label_ar' => 'عرض الدورات المتاحة',
                'type' => 'both',
                'routes' => ['available-courses.index', 'courses.apply', 'courses.apply.store', 'courses.application.status', 'courses.application.edit', 'courses.application.update'],
                'nav' => ['academic.available_courses'],
            ],
        ],
    ],

    'curriculum' => [
        'scope' => 'course',
        'label_en' => 'Curriculum',
        'label_ar' => 'المنهج',
        'sort' => 20,
        'permissions' => [
            'curriculum.view' => [
                'label_en' => 'View curriculum',
                'label_ar' => 'عرض المنهج',
                'type' => 'both',
                'routes' => ['curriculum.index', 'curriculum.show'],
                'nav' => ['academic.curriculum'],
            ],
            'curriculum.manage' => [
                'label_en' => 'Manage curriculum',
                'label_ar' => 'إدارة المنهج',
                'type' => 'both',
                'routes' => [
                    'curriculum.admin', 'courses.update-details', 'curriculum.attach-module',
                    'curriculum.create-attach-module', 'curriculum.detach-module', 'curriculum.update-module',
                    'curriculum.end-module', 'modules.*', 'sessions.*', 'lectures.*', 'lecture-materials.*',
                    'sessions.close-attendance', 'sessions.attendance.*', 'sessions.notify-students',
                    'sessions.notify-next', 'sessions.toggle-notify',
                ],
                'nav' => ['academic.curriculum', 'academic.sessions', 'academic.modules'],
            ],
            'session.notify' => [
                'label_en' => 'Send session reminders to students',
                'label_ar' => 'إرسال تذكيرات الجلسة للطلاب',
                'type' => 'endpoint',
                'routes' => ['sessions.notify-students', 'sessions.notify-next', 'sessions.toggle-notify'],
            ],
        ],
    ],

    'assignments' => [
        'scope' => 'course',
        'label_en' => 'Assignments',
        'label_ar' => 'الواجبات',
        'sort' => 30,
        'permissions' => [
            'assignment.view' => [
                'label_en' => 'View assignments',
                'label_ar' => 'عرض الواجبات',
                'type' => 'both',
                'routes' => ['assignments.index', 'assignments.show'],
                'nav' => ['academic.assignments'],
            ],
            'assignment.manage' => [
                'label_en' => 'Manage assignments',
                'label_ar' => 'إدارة الواجبات',
                'type' => 'both',
                'routes' => ['assignments.dashboard', 'assignments.create', 'assignments.store', 'assignments.edit', 'assignments.update', 'assignments.destroy', 'assignments.status'],
                'nav' => ['academic.assignments'],
            ],
            'assignment.submit' => [
                'label_en' => 'Submit assignments',
                'label_ar' => 'تسليم الواجبات',
                'type' => 'endpoint',
                'routes' => ['assignments.submit', 'assignments.update-submission'],
            ],
            'assignment.grade' => [
                'label_en' => 'Grade assignments',
                'label_ar' => 'تصحيح الواجبات',
                'type' => 'endpoint',
                'routes' => ['assignments.grade'],
            ],
        ],
    ],

    'exams' => [
        'scope' => 'course',
        'label_en' => 'Exams',
        'label_ar' => 'الامتحانات',
        'sort' => 40,
        'permissions' => [
            'exam.view' => [
                'label_en' => 'View exams',
                'label_ar' => 'عرض الامتحانات',
                'type' => 'both',
                'routes' => ['exams.index'],
                'nav' => ['academic.exams.view'],
            ],
            'exam.take' => [
                'label_en' => 'Take exams',
                'label_ar' => 'أداء الامتحانات',
                'type' => 'endpoint',
                'routes' => ['exams.attempt.*'],
            ],
            'exam.author' => [
                'label_en' => 'Author exams',
                'label_ar' => 'إنشاء الامتحانات',
                'type' => 'both',
                'routes' => ['exams.dashboard', 'exams.store', 'exams.update', 'exams.destroy', 'exams.builder', 'exams.questions.*', 'exams.publish'],
                'nav' => ['academic.exams.manage'],
            ],
            'exam.schedule' => [
                'label_en' => 'Schedule exams',
                'label_ar' => 'جدولة الامتحانات',
                'type' => 'endpoint',
                'routes' => ['exams.schedule', 'exams.admin-dashboard'],
            ],
            'exam.grade' => [
                'label_en' => 'Grade exams',
                'label_ar' => 'تصحيح الامتحانات',
                'type' => 'both',
                'routes' => ['exams.grades', 'exams.grades.*', 'exam-results.update'],
                'nav' => ['academic.exams.manage'],
            ],
            'exam.proctor' => [
                'label_en' => 'Proctor exams',
                'label_ar' => 'مراقبة الامتحانات',
                'type' => 'endpoint',
                'routes' => ['exams.attempt.proctor'],
            ],
        ],
    ],

    'grades' => [
        'scope' => 'course',
        'label_en' => 'Grades',
        'label_ar' => 'الدرجات',
        'sort' => 50,
        'permissions' => [
            'grade.view' => [
                'label_en' => 'View grades',
                'label_ar' => 'عرض الدرجات',
                'type' => 'both',
                'routes' => ['grades.show', 'courses.final-grades'],
                'nav' => ['academic.grades'],
            ],
            'grade.manage' => [
                'label_en' => 'Manage grades',
                'label_ar' => 'إدارة الدرجات',
                'type' => 'both',
                'routes' => ['grades.admin', 'grades.report', 'grade-categories.*', 'grade-items.*'],
                'nav' => ['academic.grades'],
            ],
        ],
    ],

    'attendance' => [
        'scope' => 'course',
        'label_en' => 'Attendance',
        'label_ar' => 'الحضور',
        'sort' => 60,
        'permissions' => [
            'attendance.view_own' => [
                'label_en' => 'View own attendance',
                'label_ar' => 'عرض حضوري',
                'type' => 'both',
                'routes' => ['attendance.my', 'attendance.user', 'attendance.mark'],
                'nav' => ['academic.attendance.my'],
            ],
            'attendance.record' => [
                'label_en' => 'Record attendance',
                'label_ar' => 'تسجيل الحضور',
                'type' => 'endpoint',
                'routes' => ['attendance.sessions', 'attendance.record'],
            ],
            'attendance.view_all' => [
                'label_en' => 'View all attendance',
                'label_ar' => 'عرض كل الحضور',
                'type' => 'both',
                'routes' => ['attendance.all', 'attendance.by-date', 'attendance.user-report'],
                'nav' => ['academic.attendance.all'],
            ],
            'attendance.report' => [
                'label_en' => 'Attendance reports',
                'label_ar' => 'تقارير الحضور',
                'type' => 'both',
                'routes' => ['attendance.report'],
                'nav' => ['academic.attendance.report'],
            ],
            'attendance.edit' => [
                'label_en' => 'Edit attendance records',
                'label_ar' => 'تعديل سجلات الحضور',
                'type' => 'endpoint',
                'routes' => ['attendance.update-status', 'attendance.update-status-post', 'attendance.update-permission-reason'],
            ],
        ],
    ],

    'announcements' => [
        'scope' => 'course',
        'label_en' => 'Announcements',
        'label_ar' => 'الإعلانات',
        'sort' => 70,
        'permissions' => [
            'announcement.view' => [
                'label_en' => 'View announcements',
                'label_ar' => 'عرض الإعلانات',
                'type' => 'both',
                'routes' => ['announcements.index', 'announcements.show', 'announcements.dismiss-banner'],
                'nav' => ['academic.announcements'],
            ],
            'announcement.manage' => [
                'label_en' => 'Manage announcements',
                'label_ar' => 'إدارة الإعلانات',
                'type' => 'both',
                'routes' => ['announcements.manage.*'],
                'nav' => ['academic.announcements.manage'],
            ],
            'announcement.publish' => [
                'label_en' => 'Publish announcements',
                'label_ar' => 'نشر الإعلانات',
                'type' => 'endpoint',
                'routes' => ['announcements.manage.publish', 'announcements.manage.resend-email', 'announcements.manage.whatsapp', 'announcements.manage.whatsapp.mark'],
            ],
        ],
    ],

    'communications' => [
        'scope' => 'course',
        'label_en' => 'Communications Reports',
        'label_ar' => 'تقارير الاتصالات',
        'sort' => 75,
        'permissions' => [
            'communications.report' => [
                'label_en' => 'View communications report',
                'label_ar' => 'عرض تقرير الاتصالات',
                'type' => 'both',
                'routes' => ['communications.report', 'communications.report.export'],
                'nav' => ['academic.communications_report'],
            ],
        ],
    ],

    'roster' => [
        'scope' => 'course',
        'label_en' => 'Student Roster',
        'label_ar' => 'قائمة الطلاب',
        'sort' => 80,
        'permissions' => [
            'roster.view' => [
                'label_en' => 'View student roster',
                'label_ar' => 'عرض قائمة الطلاب',
                'type' => 'both',
                'routes' => ['students.roster', 'students.birthdays'],
                'nav' => ['academic.roster', 'academic.birthdays'],
            ],
            'roster.announce' => [
                'label_en' => 'Send roster announcements',
                'label_ar' => 'إرسال إعلانات القائمة',
                'type' => 'endpoint',
                'routes' => ['students.roster.announce'],
            ],
        ],
    ],

    'graduation' => [
        'scope' => 'course',
        'label_en' => 'Graduation & Closing',
        'label_ar' => 'التخرج والإغلاق',
        'sort' => 90,
        'permissions' => [
            'graduation.view' => [
                'label_en' => 'View graduation reports',
                'label_ar' => 'عرض تقارير التخرج',
                'type' => 'both',
                'routes' => ['graduation.index', 'graduation.show', 'graduation.export'],
                'nav' => ['academic.graduation'],
            ],
            'graduation.configure' => [
                'label_en' => 'Configure graduation',
                'label_ar' => 'إعداد التخرج',
                'type' => 'endpoint',
                'routes' => ['admin.graduation-settings.*', 'courses.closing.grace'],
            ],
            'course.close' => [
                'label_en' => 'Close course',
                'label_ar' => 'إغلاق الدورة',
                'type' => 'both',
                'routes' => ['courses.closing.*'],
                'nav' => ['academic.graduation'],
            ],
            'certificate.manage' => [
                'label_en' => 'Manage certificates',
                'label_ar' => 'إدارة الشهادات',
                'type' => 'endpoint',
                'routes' => ['courses.certificate-template.*'],
            ],
            'email_templates.manage' => [
                'label_en' => 'Manage course email templates',
                'label_ar' => 'إدارة قوالب بريد الدورة',
                'type' => 'both',
                'routes' => ['courses.email-templates.*'],
                'nav' => ['academic.email_templates'],
            ],
            'certificate.download' => [
                'label_en' => 'Download certificates',
                'label_ar' => 'تحميل الشهادات',
                'type' => 'endpoint',
                'routes' => ['certificates.download'],
            ],
        ],
    ],

    'feedback' => [
        'scope' => 'course',
        'label_en' => 'Feedback Surveys',
        'label_ar' => 'استبيانات التقييم',
        'sort' => 100,
        'permissions' => [
            'feedback.view' => [
                'label_en' => 'View/submit feedback',
                'label_ar' => 'عرض/تقديم التقييم',
                'type' => 'both',
                'routes' => ['feedback.index', 'feedback.surveys.show', 'feedback.surveys.submit'],
                'nav' => ['academic.feedback'],
            ],
            'feedback.manage' => [
                'label_en' => 'Manage feedback surveys',
                'label_ar' => 'إدارة الاستبيانات',
                'type' => 'both',
                'routes' => ['feedback.surveys.create', 'feedback.surveys.store', 'feedback.surveys.edit', 'feedback.surveys.update', 'feedback.surveys.questions.*', 'feedback.surveys.publish', 'feedback.surveys.close', 'feedback.surveys.destroy'],
                'nav' => ['academic.feedback'],
            ],
            'feedback.report' => [
                'label_en' => 'Feedback reports',
                'label_ar' => 'تقارير التقييم',
                'type' => 'endpoint',
                'routes' => ['feedback.surveys.report', 'feedback.surveys.report.*'],
            ],
        ],
    ],

    'live_quiz' => [
        'scope' => 'course',
        'label_en' => 'Live Quiz',
        'label_ar' => 'الاختبار الحي',
        'sort' => 110,
        'permissions' => [
            'live_quiz.play' => [
                'label_en' => 'Play live quiz',
                'label_ar' => 'المشاركة في الاختبار الحي',
                'type' => 'both',
                'routes' => ['live-quiz.play.*', 'live-quiz.index'],
                'nav' => ['academic.live_quiz'],
            ],
            'live_quiz.host' => [
                'label_en' => 'Host live quiz',
                'label_ar' => 'استضافة الاختبار الحي',
                'type' => 'endpoint',
                'routes' => ['live-quiz.host.*'],
            ],
            'live_quiz.manage' => [
                'label_en' => 'Manage live quizzes',
                'label_ar' => 'إدارة الاختبارات الحية',
                'type' => 'both',
                'routes' => ['live-quiz.create', 'live-quiz.store', 'live-quiz.show', 'live-quiz.destroy', 'live-quiz.builder', 'live-quiz.questions.*'],
                'nav' => ['academic.live_quiz'],
            ],
        ],
    ],

    'events' => [
        'scope' => 'both',
        'label_en' => 'Events',
        'label_ar' => 'الفعاليات',
        'sort' => 120,
        'permissions' => [
            'events.view' => [
                'label_en' => 'View events',
                'label_ar' => 'عرض الفعاليات',
                'type' => 'both',
                'routes' => ['events.index', 'events.show'],
                'nav' => ['academic.events'],
            ],
            'events.reserve' => [
                'label_en' => 'Reserve events',
                'label_ar' => 'حجز الفعاليات',
                'type' => 'both',
                'routes' => ['events.reserve', 'events.cancel', 'events.my-reservations'],
                'nav' => ['academic.events'],
            ],
            'events.admin' => [
                'label_en' => 'Administer events',
                'label_ar' => 'إدارة الفعاليات',
                'type' => 'both',
                'routes' => ['events.admin.*'],
                'nav' => ['academic.events'],
            ],
            'events.check_in' => [
                'label_en' => 'Event check-in',
                'label_ar' => 'تسجيل حضور الفعاليات',
                'type' => 'endpoint',
                'routes' => ['events.admin.check-in', 'events.admin.check-in.record', 'events.check-in.verify'],
            ],
        ],
    ],

    'role_management' => [
        'scope' => 'course',
        'label_en' => 'Role Management',
        'label_ar' => 'إدارة الأدوار',
        'sort' => 130,
        'permissions' => [
            'role.manage' => [
                'label_en' => 'Manage course roles',
                'label_ar' => 'إدارة أدوار الدورة',
                'type' => 'both',
                'routes' => ['courses.roles.*', 'roles.hub'],
                'nav' => ['system.course_roles'],
            ],
            'user.assign_role' => [
                'label_en' => 'Assign users to roles',
                'label_ar' => 'تعيين المستخدمين للأدوار',
                'type' => 'both',
                'routes' => ['courses.roles.assignments.*', 'user-course-roles.*', 'roles.hub'],
                'nav' => ['system.roles'],
            ],
        ],
    ],

    'church_management' => [
        'scope' => 'system',
        'label_en' => 'Church Management',
        'label_ar' => 'إدارة الكنيسة',
        'sort' => 140,
        'permissions' => [
            'church.configure' => [
                'label_en' => 'Configure church settings',
                'label_ar' => 'إعدادات الكنيسة',
                'type' => 'both',
                'routes' => [],
            ],
            'church.members.manage' => [
                'label_en' => 'Manage church members',
                'label_ar' => 'إدارة أعضاء الكنيسة',
                'type' => 'both',
                'routes' => [],
            ],
            'church.role.manage' => [
                'label_en' => 'Manage church roles & permissions',
                'label_ar' => 'إدارة أدوار وصلاحيات الكنيسة',
                'type' => 'both',
                'routes' => [],
            ],
            'priest.view' => [
                'label_en' => 'View priests',
                'label_ar' => 'عرض الكهنة',
                'type' => 'both',
                'routes' => ['church.priests.index', 'church.priests.show'],
                'nav' => ['church.priests'],
            ],
            'priest.manage' => [
                'label_en' => 'Manage priests',
                'label_ar' => 'إدارة الكهنة',
                'type' => 'both',
                'routes' => ['church.priests.*'],
                'nav' => ['church.priests'],
            ],
            'confession.view' => [
                'label_en' => 'View confession calendar',
                'label_ar' => 'عرض جدول الاعتراف',
                'type' => 'both',
                'routes' => ['church.confession.index', 'church.confession.show'],
                'nav' => ['church.confession'],
            ],
            'confession.manage' => [
                'label_en' => 'Manage own confession slots',
                'label_ar' => 'إدارة مواعيد الاعتراف',
                'type' => 'both',
                'routes' => ['church.confession.*'],
                'nav' => ['church.confession'],
            ],
            'confession.book' => [
                'label_en' => 'Book a confession slot',
                'label_ar' => 'حجز موعد اعتراف',
                'type' => 'endpoint',
                'routes' => ['church.confession.book', 'church.confession.bookings.cancel'],
            ],
            'home_visit.view' => [
                'label_en' => 'View home visits',
                'label_ar' => 'عرض الزيارات المنزلية',
                'type' => 'both',
                'routes' => ['church.home-visits.index', 'church.home-visits.show'],
                'nav' => ['church.home_visits'],
            ],
            'home_visit.manage' => [
                'label_en' => 'Manage home visits',
                'label_ar' => 'إدارة الزيارات المنزلية',
                'type' => 'both',
                'routes' => ['church.home-visits.*'],
                'nav' => ['church.home_visits'],
            ],
            'finance.payroll.view' => [
                'label_en' => 'View payroll',
                'label_ar' => 'عرض الرواتب',
                'type' => 'both',
                'routes' => ['church.finance.payroll.index', 'church.finance.payroll.show'],
                'nav' => ['church.payroll'],
            ],
            'finance.payroll.manage' => [
                'label_en' => 'Manage payroll',
                'label_ar' => 'إدارة الرواتب',
                'type' => 'both',
                'routes' => ['church.finance.payroll.*'],
                'nav' => ['church.payroll'],
            ],
            'finance.money_in.view' => [
                'label_en' => 'View money in',
                'label_ar' => 'عرض الإيرادات',
                'type' => 'both',
                'routes' => ['church.finance.money-in.index'],
                'nav' => ['church.money_in'],
            ],
            'finance.money_in.manage' => [
                'label_en' => 'Manage money in',
                'label_ar' => 'إدارة الإيرادات',
                'type' => 'both',
                'routes' => ['church.finance.money-in.*'],
                'nav' => ['church.money_in'],
            ],
        ],
    ],

    'service_management' => [
        'scope' => 'service',
        'label_en' => 'Service Management',
        'label_ar' => 'إدارة الخدمة',
        'sort' => 135,
        'permissions' => [
            'service.view' => [
                'label_en' => 'View service',
                'label_ar' => 'عرض الخدمة',
                'type' => 'both',
                'routes' => ['hubs.service', 'roles.hub', 'services.select', 'services.roster'],
                'nav' => ['service.hub'],
            ],
            'service.manage' => [
                'label_en' => 'Manage service settings',
                'label_ar' => 'إدارة إعدادات الخدمة',
                'type' => 'both',
                'routes' => ['services.*', 'roles.hub', 'hubs.service'],
            ],
            'service.member.add' => [
                'label_en' => 'Add members to service',
                'label_ar' => 'إضافة أعضاء للخدمة',
                'type' => 'endpoint',
                'routes' => ['services.members.store', 'roles.hub'],
            ],
            'service.member.remove' => [
                'label_en' => 'Remove members from service',
                'label_ar' => 'إزالة أعضاء من الخدمة',
                'type' => 'endpoint',
                'routes' => ['services.members.destroy', 'roles.hub'],
            ],
            'service.member.add_cross' => [
                'label_en' => 'Add existing service users to another service',
                'label_ar' => 'إضافة مستخدمي خدمة إلى خدمة أخرى',
                'type' => 'endpoint',
                'routes' => ['services.members.cross', 'roles.hub'],
            ],
            'service.role.manage' => [
                'label_en' => 'Manage service roles',
                'label_ar' => 'إدارة أدوار الخدمة',
                'type' => 'both',
                'routes' => ['services.roles.*', 'roles.hub'],
            ],
            'service.user.assign_role' => [
                'label_en' => 'Assign service roles',
                'label_ar' => 'تعيين أدوار الخدمة',
                'type' => 'both',
                'routes' => ['services.members.*', 'roles.hub'],
            ],
            'service_application.review' => [
                'label_en' => 'Review service applications',
                'label_ar' => 'مراجعة طلبات الانضمام للخدمة',
                'type' => 'both',
                'routes' => ['admin.service-applications.*', 'hubs.service'],
                'nav' => ['service.applications'],
            ],
            'service_application.form_builder' => [
                'label_en' => 'Manage service application forms',
                'label_ar' => 'إدارة نماذج طلبات الخدمة',
                'type' => 'both',
                'routes' => ['services.apply', 'services.apply.store'],
            ],
        ],
    ],

    'user_lifecycle' => [
        'scope' => 'system',
        'label_en' => 'User Lifecycle',
        'label_ar' => 'دورة حياة المستخدم',
        'sort' => 200,
        'permissions' => [
            'user.approve' => [
                'label_en' => 'Approve users',
                'label_ar' => 'الموافقة على المستخدمين',
                'type' => 'endpoint',
                'routes' => ['admin.users.approve'],
            ],
            'registration.review' => [
                'label_en' => 'Review registration applications',
                'label_ar' => 'مراجعة طلبات التسجيل',
                'type' => 'both',
                'routes' => ['admin.registration-applications.*'],
                'nav' => ['system.registration'],
            ],
            'registration.send_link' => [
                'label_en' => 'Send registration links',
                'label_ar' => 'إرسال روابط التسجيل',
                'type' => 'endpoint',
                'routes' => ['user-course-roles.send-registration-link'],
            ],
        ],
    ],

    'course_applications' => [
        // both: grantable on course admin roles (roles hub) and system roles
        'scope' => 'both',
        'label_en' => 'Course Applications',
        'label_ar' => 'طلبات الالتحاق بالدورات',
        'sort' => 210,
        'permissions' => [
            'course_application.review' => [
                'label_en' => 'Review course applications',
                'label_ar' => 'مراجعة طلبات الدورات',
                'type' => 'both',
                'routes' => ['admin.course-applications.*'],
                'nav' => ['system.course_applications'],
            ],
            'course_application.form_builder' => [
                'label_en' => 'Build application forms',
                'label_ar' => 'بناء نماذج التقديم',
                'type' => 'both',
                'routes' => ['admin.courses.application-forms.*', 'admin.courses.application-form.*'],
                'nav' => ['system.application_forms'],
            ],
        ],
    ],

    'platform_config' => [
        'scope' => 'system',
        'label_en' => 'Platform Configuration',
        'label_ar' => 'إعدادات المنصة',
        'sort' => 220,
        'permissions' => [
            'translation.manage' => [
                'label_en' => 'Manage translations',
                'label_ar' => 'إدارة الترجمات',
                'type' => 'both',
                'routes' => ['admin.translations.*'],
                'nav' => ['system.translations'],
            ],
            'attendance.configure' => [
                'label_en' => 'Configure attendance settings',
                'label_ar' => 'إعدادات الحضور',
                'type' => 'both',
                'routes' => ['admin.attendance-settings.*'],
                'nav' => ['system.attendance_settings'],
            ],
            'graduation.settings' => [
                'label_en' => 'Graduation settings',
                'label_ar' => 'إعدادات التخرج',
                'type' => 'both',
                'routes' => ['admin.graduation-settings.*'],
                'nav' => ['system.graduation_settings'],
            ],
            'profile_photo.review' => [
                'label_en' => 'Review profile photos',
                'label_ar' => 'مراجعة صور الملف الشخصي',
                'type' => 'both',
                'routes' => ['admin.profile-photos.*'],
                'nav' => ['system.profile_photos'],
            ],
        ],
    ],

    'platform_roles' => [
        'scope' => 'system',
        'label_en' => 'Platform Roles',
        'label_ar' => 'أدوار المنصة',
        'sort' => 230,
        'permissions' => [
            'system.role.manage' => [
                'label_en' => 'Manage system roles',
                'label_ar' => 'إدارة أدوار النظام',
                'type' => 'both',
                'routes' => ['superadmin.roles.*', 'roles.*', 'roles.hub'],
                'nav' => ['system.roles'],
            ],
            'system.user.assign' => [
                'label_en' => 'Assign system roles',
                'label_ar' => 'تعيين أدوار النظام',
                'type' => 'both',
                'routes' => ['roles.hub', 'superadmin.system-roles.*'],
                'nav' => ['system.roles'],
            ],
        ],
    ],

    'superadmin' => [
        'scope' => 'system',
        'label_en' => 'SuperAdmin',
        'label_ar' => 'المشرف الأعلى',
        'sort' => 240,
        'permissions' => [
            'platform.audit' => [
                'label_en' => 'View audit logs',
                'label_ar' => 'عرض سجلات التدقيق',
                'type' => 'both',
                'routes' => ['superadmin.audit.*'],
                'nav' => ['system.audit'],
                'system_only' => true,
            ],
            'platform.impersonate' => [
                'label_en' => 'Impersonate users',
                'label_ar' => 'انتحال هوية المستخدمين',
                'type' => 'endpoint',
                'routes' => ['superadmin.impersonate', 'superadmin.impersonate.stop'],
                'system_only' => true,
            ],
            'platform.session_flush' => [
                'label_en' => 'Flush all sessions',
                'label_ar' => 'إنهاء كل الجلسات',
                'type' => 'endpoint',
                'routes' => ['superadmin.sessions.flush-all'],
                'system_only' => true,
            ],
            'platform.course_crud' => [
                'label_en' => 'Create/delete courses',
                'label_ar' => 'إنشاء/حذف الدورات',
                'type' => 'endpoint',
                'routes' => ['superadmin.courses.store', 'superadmin.courses.destroy', 'superadmin.courses'],
                'system_only' => true,
            ],
            'platform.service_crud' => [
                'label_en' => 'Create/manage services',
                'label_ar' => 'إنشاء/إدارة الخدمات',
                'type' => 'both',
                'routes' => ['admin.services.*'],
                'nav' => ['service.manage_panel'],
                'system_only' => true,
            ],
            'platform.group_visibility' => [
                'label_en' => 'Manage permission group visibility',
                'label_ar' => 'إدارة ظهور مجموعات الصلاحيات',
                'type' => 'both',
                'routes' => ['roles.hub', 'superadmin.group-visibility.*'],
                'nav' => ['system.group_visibility'],
                'system_only' => true,
            ],
            'platform.role_templates' => [
                'label_en' => 'Manage role templates',
                'label_ar' => 'إدارة قوالب الأدوار',
                'type' => 'both',
                'routes' => ['roles.hub', 'superadmin.templates.*'],
                'nav' => ['system.templates'],
                'system_only' => true,
            ],
        ],
    ],
];
