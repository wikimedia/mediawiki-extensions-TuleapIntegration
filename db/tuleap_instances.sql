CREATE TABLE IF NOT EXISTS /*$wgDBprefix*/tuleap_instances (
	`ti_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`ti_name` VARCHAR(255) NOT NULL,
	`ti_status` VARCHAR(255) NOT NULL,
	`ti_created_at` VARCHAR(14) NOT NULL,
	`ti_directory` VARCHAR(255) NULL,
    `ti_database` VARCHAR(255) NULL,
	`ti_script_path` VARCHAR(255) NULL,
    `ti_data` BLOB NULL DEFAULT '',
    PRIMARY KEY ( `ti_id` )
);
