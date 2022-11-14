<?php

// Contains default names and types for standard WordPress fields. Can be filtered with wpf_meta_fields.
$wp_fields['first_name'] = array(
	'type'  => 'text',
	'label' => __( 'First Name', 'wp-fusion' ),
);

$wp_fields['last_name'] = array(
	'type'  => 'text',
	'label' => __( 'Last Name', 'wp-fusion' ),
);

$wp_fields['user_email'] = array(
	'type'  => 'text',
	'label' => __( 'E-mail Address', 'wp-fusion' ),
);

$wp_fields['display_name'] = array(
	'type'  => 'text',
	'label' => __( 'Profile Display Name', 'wp-fusion' ),
);

$wp_fields['user_nicename'] = array(
	'type'  => 'text',
	'label' => __( 'Nicename', 'wp-fusion' ),
);

$wp_fields['nickname'] = array(
	'type'  => 'text',
	'label' => __( 'Nickname', 'wp-fusion' ),
);

$wp_fields['user_login'] = array(
	'type'  => 'text',
	'label' => __( 'Username', 'wp-fusion' ),
);

$wp_fields['user_id'] = array(
	'type'   => 'integer',
	'label'  => __( 'User ID', 'wp-fusion' ),
	'pseudo' => true,
);

$wp_fields['locale'] = array(
	'type'  => 'text',
	'label' => __( 'Language', 'wp-fusion' ),
);

$wp_fields['role'] = array(
	'type'  => 'text',
	'label' => __( 'User Role', 'wp-fusion' ),
);

$wp_fields['wp_capabilities'] = array(
	'type'  => 'multiselect',
	'label' => __( 'User Capabilities', 'wp-fusion' ),
);

$wp_fields['user_pass'] = array(
	'type'  => 'text',
	'label' => __( 'Password', 'wp-fusion' ),
);

$wp_fields['user_registered'] = array(
	'type'   => 'date',
	'label'  => __( 'User Registered', 'wp-fusion' ),
	'pseudo' => true,
);

$wp_fields['description'] = array(
	'type'  => 'textarea',
	'label' => __( 'Biography', 'wp-fusion' ),
);

$wp_fields['user_url'] = array(
	'type'  => 'text',
	'label' => __( 'Website (URL)', 'wp-fusion' ),
);

$wp_fields['ip'] = array(
	'type'   => 'text',
	'label'  => __( 'IP Address', 'wp-fusion' ),
	'pseudo' => true,
);
