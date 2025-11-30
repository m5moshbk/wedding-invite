<?php
/****************************************
 * إعدادات تمارا Tamara
 ****************************************/

// عنوان الـ API (غالباً production، لو حسابك تجريبي استبدلها بـ api-sandbox.tamara.co)
define('TAMARA_API_BASE', 'https://api.tamara.co');

// الصق هنا الـ Merchant Token (JWT) الطويل كما هو من لوحة تمارا
define('TAMARA_MERCHANT_TOKEN', 'هنا_الصق_الـ_Token_الكامل');

// الـ Public Key / ID الذي أرسلته لي
define('TAMARA_PUBLIC_KEY', '0a822250-d856-4ff1-9e9a-2d74d6d44caa');


/****************************************
 * إعدادات إمكان Emkan
 ****************************************/

// الدومين الظاهر في أسفل الصورة:
define('EMKAN_API_BASE', 'https://ants.emkanfinance.com.sa');

// API Key من لوحة إمكان (اللي يبدأ بـ Pu6gG6E…)
define('EMKAN_API_KEY', 'Pu6gG6EH8nJEdvcCbUm_Y6dZJZka');

// API Secret من لوحة إمكان (اللي يبدأ بـ ltSu6H…)
define('EMKAN_API_SECRET', 'ltSu6HKDGfGMQDVkAgA4lu5Clgca');


/****************************************
 * إعدادات عامة مشتركة
 ****************************************/

// العملة الافتراضية
define('BNPL_DEFAULT_CURRENCY', 'SAR');