<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Truncated @since 2.3.2, the contents is moved in template_helper.php
 * This file sits here only for backward compatibilities, the Codeigniter framework auto load the file because of the app_ prefix, if removed will throw errors upon upgrade because of the App_ prefix change
 * For example, after 2-3 years, the file should be removed because probably no one will be using 2.3. version :)
 */



function approval_levels_required($entity_type, $amount)
{
    $CI =& get_instance();

    $rule = $CI->db
        ->where('entity_type', $entity_type)
        ->where('min_amount <=', $amount)
        ->where('max_amount >=', $amount)
        ->get('tblapproval_rules')
        ->row();

    return $rule ? (int)$rule->total_levels : 0;
}
