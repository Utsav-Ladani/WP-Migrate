<?php
/**
 * Plugin Name:     Migrate
 * Plugin URI:      PLUGIN SITE HERE
 * Description:     PLUGIN DESCRIPTION HERE
 * Author:          YOUR NAME HERE
 * Author URI:      YOUR SITE HERE
 * Text Domain:     migrate
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         Migrate
 */

// Your code starts here.

if ( defined( 'WP_CLI' ) ) {
	WP_CLI::add_command( 'mg', 'migrate' );
}

function migrate( $args, $assoc_args ) {
	global $wpdb;

	if( isset( $assoc_args['clean'] ) && $assoc_args['clean'] == 'true' ) {
		$sql = "DROP TABLE IF EXISTS `{$wpdb->prefix}migrate_post`";
		$wpdb->query( $sql );

		$sql = "CREATE TABLE `{$wpdb->prefix}migrate_post` (
		`id` int(11) NOT NULL AUTO_INCREMENT,
		`post_id` int(11) NOT NULL,
		`post_title` varchar(255) NOT NULL,
		`post_content` longtext NOT NULL,
		`post_date` datetime NOT NULL,
		`post_status` varchar(20) NOT NULL,
		`post_name` varchar(200) NOT NULL,
		`post_type` varchar(20) NOT NULL,
		`post_author` int(11) NOT NULL,
		`post_modified` datetime NOT NULL,
		`post_parent` int(11) NOT NULL,
		`guid` varchar(255) NOT NULL,
		PRIMARY KEY (`id`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
		$wpdb->query( $sql );

		update_option( 'previously_migrated_post', 0 );

		WP_CLI::success( "Cleaned previous data" );
	}

	$current_page = 0;
	$post_per_page = 2;
	$migrate_in_this_run = 0;
	$previously_migrated_post = get_option( 'previously_migrated_post', 0 );
	$updated_post = 0;

	if ( isset( $assoc_args['update'] ) && $assoc_args['update'] == 'true' ) {
		$previously_migrated_post = 0;
	}

	while( true ) {
		$sql = "SELECT * FROM {$wpdb->prefix}posts ORDER BY 'post_id' DESC LIMIT " . $previously_migrated_post . ", {$post_per_page}";
		$posts = $wpdb->get_results( $sql );

		if( empty( $posts ) ) {
			break;
		}

		foreach( $posts as $post ) {
			// get post with same id from wp_migrate_post table
			$sql        = "SELECT * FROM `{$wpdb->prefix}migrate_post` WHERE `post_id` = {$post->ID}";
			$exist_post = $wpdb->get_row( $sql );

			if ( ! empty( $exist_post ) ) {
				if ( $exist_post->post_modified == $post->post_modified ) {
					WP_CLI::line( "Post with ID: {$post->ID} is already migrated" );
					continue;
				} else {
					$sql = "DELETE FROM `{$wpdb->prefix}migrate_post` WHERE `post_id` = {$post->ID}";
					$wpdb->query( $sql );

					migrate_post( $post );
					$updated_post++;
					WP_CLI::line( "Updated post with ID: {$post->ID}" );
				}
			} else if ( $post->post_type == 'revision' ) {
				WP_CLI::line( "Post with ID: {$post->ID} is revision" );
				continue;
			} else {
				migrate_post( $post );
				WP_CLI::line( "Migrated post with ID: {$post->ID}" );
			}
		}
		WP_CLI::line( "Migrated " . count($posts) . " posts,  page: " . $current_page );
		WP_CLI::line( "--------------------------------------" );

		$current_page++;
		$previously_migrated_post += count( $posts );
		$migrate_in_this_run += count( $posts );
		update_option( 'previously_migrated_post', $previously_migrated_post );
	}
	WP_CLI::success( "Updated {$updated_post} posts" );
	WP_CLI::success( "Migrated {$migrate_in_this_run} posts" );
}

function migrate_post( $post ) {
	global $wpdb;

	$sql = "INSERT INTO `{$wpdb->prefix}migrate_post` (
				`post_id`,
				`post_title`,
				`post_content`,
				`post_date`,
				`post_status`,
				`post_name`,
				`post_type`,
				`post_author`,
				`post_modified`,
				`post_parent`,
				`guid`
			) VALUES (
				'{$post->ID}',
				'{$post->post_title}',
				'{$post->post_content}',
				'{$post->post_date}',
				'{$post->post_status}',
				'{$post->post_name}',
				'{$post->post_type}',
				'{$post->post_author}',
				'{$post->post_modified}',
				'{$post->post_parent}',
				'{$post->guid}'
			)";
	$wpdb->query( $sql );
}
