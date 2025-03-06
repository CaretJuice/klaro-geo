<?php
function klaro_geo_get_default_geo_settings() {
    // Default settings structure with only template settings
    return array(
        'default_template' => 'default',
        'countries' => array(
            'US' => array(
                'template' => 'default',
                'regions' => array()
            ),
            'UK' => array(
                'template' => 'default',
                'regions' => array()
            ),
            'DE' => array(
                'template' => 'default',
                'regions' => array()
            )
        )
    );
}

function klaro_geo_get_default_templates() {
    return array(
        'default' => array(
            'name' => 'Default Privacy',
            'config' => array(
                'required' => false,
                'acceptAll' => true,
                'hideDeclineAll' => false,
                'hideLearnMore' => false,
                'noticeAsModal' => false,
                'default' => false
            )
        ),
        'strict' => array(
            'name' => 'Strict Privacy',
            'config' => array(
                'required' => false,
                'acceptAll' => true,
                'hideDeclineAll' => false,
                'hideLearnMore' => false,
                'noticeAsModal' => true,
                'default' => false
            )
        ),
        'relaxed' => array(
            'name' => 'Relaxed Privacy',
            'config' => array(
                'required' => false,
                'acceptAll' => true,
                'hideDeclineAll' => true,
                'hideLearnMore' => false,
                'noticeAsModal' => false,
                'default' => true
            )
        )
    );
}
?>