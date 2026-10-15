<?php

return array_values(array_filter([
    App\Providers\AppServiceProvider::class,

    // SaaS-only code lives in app/Saas. A standalone build deletes that folder and this line then drops out.
    class_exists(App\Saas\SaasServiceProvider::class) ? App\Saas\SaasServiceProvider::class : null,
]));
