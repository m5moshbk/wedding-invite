<?php
// config.php
// إعدادات بوابات BNPL – تمارا + إمكان

/******************************
 * إعدادات Tamara
 ******************************/

// بيئة Tamara (اختبار Sandbox حالياً)
// عند الانتقال للإنتاج غيّر الرابط إلى: https://api.tamara.co
define('TAMARA_API_BASE', 'https://api-sandbox.tamara.co');

// Merchant Token (JWT) الخاص بتمارا – سري جداً
define('TAMARA_MERCHANT_TOKEN', 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhY2NvdW50SWQiOiI2MjBkYmViYS1hYTAzLTQ2ZDEtYWJhMy04YWM3MjVlMjYzNjMiLCJ0eXBlIjoibWVyY2hhbnQiLCJzYWx0IjoiODBkNzQ4MDUzZmMzYTNlNTYyYjBkNWUzZmRmN2IzMzkiLCJyb2xlcyI6WyJST0xFX01FUkNIQU5UIl0sImlhdCI6MTY5MTU2MTIxNCwiaXNzIjoiVGFtYXJhIFBQIn0.U2jFqpF4_cIPurtSEJqO2wxNVOI6VM_enE20HJdU5OjrJWskdJ7B8B8t6c7ArGT_wpmJIAb0WXfU73fPVHNS2Ofu_MMjHJ8eA-3tCqtprzW5esTClxX6Ym9QH3SGezDpljt2b20Wd10BGUEmwD2mKUxb06IlPgN_Amam30fmJni7nc5V9GR4nx0GGPtec-T9yOA9O6BjZ3DsbKlApVwWxCPEEeuuhgQ2SmPpa1JCvjhUs9wQ5DsuJACJxaJ0PfqKQwXGTK56VzgCV5MLlTV7c9_MQDSsRTHa3e8XM7I2DRBDvErp0LrMtJc2EWwIS_VUIQfU37P_94pWcHKqU591RQ');

// Public Key / ID لتمارا (ستحتاجه لاحقاً للتحقق من Webhook)
define('TAMARA_PUBLIC_KEY', '0a822250-d856-4ff1-9e9a-2d74d6d44caa');


/******************************
 * إعدادات إمكان Emkan
 ******************************/

// قاعدة عنوان إمكان – نضع الدومين من الصورة (قد يتغير الـ path حسب مستنداتهم)
define('EMKAN_API_BASE', 'https://ants.emkanfinance.com.sa');

// API Key من لوحة إمكان
define('EMKAN_API_KEY', 'Pu6gG6EH8nJEdvcCbUm_Y6dZJZka');

// API Secret من لوحة إمكان
define('EMKAN_API_SECRET', 'ltSu6HKDGfGMQDVkAgA4lu5Clgca');


/******************************
 * إعداد عام
 ******************************/
define('BNPL_DEFAULT_CURRENCY', 'SAR');