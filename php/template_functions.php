<?php
// Set Grobal Template Variables
app()->twig->addGlobal('STATIC_URL', URL . 'static');
app()->twig->addGlobal('MEDIA_URL', URL . 'media');
app()->twig->addGlobal('USER', app('auth')->user());
app()->twig->addGlobal('SYSTEM_TIMEZONE_OFFSET', date('P'));
app()->twig->addGlobal('SETTINGS', SETTINGS);
app()->twig->addGlobal('IMAGE_SIZE', IMAGE_SIZE);
app()->twig->addGlobal('SITE_URL', URL);
app()->twig->addGlobal('LANG', app()->lang);
app()->twig->addGlobal('CV', CV);

// Custom Template Functions

// Quck accss URL
$url_func = new \Twig\TwigFunction('url', function ($url, $params=false) {
    if ($params) {
        return route($url, $params);
    }else{
        return route($url);
    }
});
app()->twig->addFunction($url_func);

// Flash messages for templates
$msg_func = new \Twig\TwigFunction('msg', function () {
    return app()->msg->display();
});
app()->twig->addFunction($msg_func);

// HTML parse functions
$filter = new \Twig\TwigFilter('htmlspecialchars_decode', 'htmlspecialchars_decode');
app()->twig->addFilter($filter);

$filter = new \Twig\TwigFilter('htmlentities', 'htmlentities');
app()->twig->addFilter($filter);

// CSRF tokens and input feils
$csrf_func = new \Twig\TwigFunction('csrf_token', function () {
    return app()->csfr->getInputToken(SECRET_KEY);
});
app()->twig->addFunction($csrf_func);

$csrf_ajax_func = new \Twig\TwigFunction('csrf_token_ajax', function () {
    return app()->csfr->getToken(SECRET_KEY);
});
app()->twig->addFunction($csrf_ajax_func);


// Quck accss URL
$translate_func = new \Twig\TwigFunction('_', function ($term) {
    return translate_term($term);
});
app()->twig->addFunction($translate_func);

?>
