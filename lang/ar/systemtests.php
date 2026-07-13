<?php

return [
    'dashboard' => 'تقرير اختبارات النظام',
    'intro' => 'خطوط اختبار آلية للمنصة بالكامل. كل خط يعمل بشكل منفصل، و«تشغيل الكل» يشغّل كل الخطوط بالتتابع. نفس الخطوط تحمي كل عملية نشر في التكامل المستمر.',

    'run_pipelines' => 'تشغيل خطوط الاختبار',
    'run_all_sequence' => 'تشغيل الكل (بالتتابع)',
    'confirm_run_all' => 'هل تريد تشغيل جميع خطوط الاختبار الآن؟ قد يستغرق ذلك بضع دقائق.',

    'latest_by_pipeline' => 'أحدث نتيجة لكل خط',
    'never_run' => 'لم يُشغّل بعد',
    'history' => 'سجل التشغيل',
    'no_history' => 'لا توجد عمليات تشغيل مسجَّلة بعد.',
    'pipeline' => 'الخط',
    'results' => 'النتائج',
    'duration' => 'المدة',
    'triggered_by' => 'شغّله',
    'view_output' => 'المخرجات',
    'output_title' => 'مخرجات تشغيل الاختبار',
    'back_to_report' => 'العودة إلى التقرير',
    'raw_output' => 'المخرجات الخام',

    // أسماء الخطوط
    'suite_unit' => 'الوحدات',
    'suite_feature' => 'الوظائف',
    'suite_smoke' => 'الواجهات والمسارات',
    'suite_api' => 'واجهة البرمجة',
    'suite_notifications' => 'الإشعارات',
    'suite_mail' => 'البريد والاتصالات الخارجية',
    'suite_tenancy' => 'عزل المستأجرين',
    'suite_load' => 'الحِمل',

    // الحالات
    'status_passed' => 'ناجح',
    'status_failed' => 'فاشل',
    'status_pending' => 'قيد الانتظار',

    // الملخصات
    'all_passed' => 'نجحت جميع الاختبارات :total',
    'failed_summary' => ':failed فاشل، :passed ناجح',
    'skipped_n' => ':n متخطّى',
    'no_tests' => 'لم تُنفّذ أي اختبارات',
    'empty_output' => 'لم يُنتج مشغّل الاختبارات أي مخرجات.',

    // الرسائل والأخطاء
    'run_completed_ok' => 'اكتمل التشغيل — نجحت جميع الخطوط.',
    'run_completed_with_failures' => 'اكتمل التشغيل مع وجود إخفاقات. راجع التقرير بالأسفل.',
    'run_failed' => 'تعذّر إكمال تشغيل الاختبارات: :message',
    'table_missing' => 'جدول system_test_runs غير موجود. نفّذ الترحيلات أولًا.',
    'phpunit_missing' => 'PHPUnit غير مُثبّت (نفّذ composer install مع تبعيات التطوير).',
    'autoload_missing' => 'محمّل الاختبارات غير متاح (نفّذ composer dump-autoload).',
    'sqlite_missing' => 'امتداد pdo_sqlite مطلوب لتشغيل خطوط الاختبار.',
];
