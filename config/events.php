<?php
return [
    'EVENT_CONTROLLER_TYPE' => [
        'before_render' => 'BeforeRender',
        'after_render' => 'AfterRender',
        'before_store' => 'BeforeStore',
        'after_store' => 'AfterStore',
        'before_restore' => 'BeforeRestore',
        'after_restore' => 'AfterRestore',
        'before_update' => 'BeforeUpdate',
        'after_update' => 'AfterUpdate',
        'before_destroy' => 'BeforeDestroy',
        'after_destroy' => 'AfterDestroy',
        'before_render_json' => 'BeforeRenderJson',
        'after_render_json' => 'AfterRenderJson',
        'before_render_xml' => 'BeforeRenderXml',
        'after_render_xml' => 'AfterRenderXml',
        'before_redirect' => 'BeforeRedirect',
        'after_redirect' => 'AfterRedirect',
        'before_create' => 'BeforeCreate',
        'after_create' => 'AfterCreate',
    ],
    'EVENT_MODEL_TYPE' => [
        'before_save' => 'saving',
        'after_save' => 'saved',
    ],
    'EVENT_OTHER_TYPE' => []
];
