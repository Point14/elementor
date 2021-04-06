<?php
namespace Elementor\Core\Files\File_Types;

use Elementor\Core\Base\Document;
use Elementor\Core\Utils\Exceptions;
use Elementor\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Elementor File Types Base.
 *
 * The File Types Base class provides base methods used by all file type handler classes.
 * These methods are used in file upl
 *
 * @since 3.3.0
 */
class Zip extends Base {

	/**
	 * Get Mime Types
	 *
	 * Return an array of mime types compatible with this file type
	 *
	 * @since 3.3.0
	 *
	 * @return array
	 */
	public function get_mime_types() {
		return [
			'application/zip',
			'application/x-zip',
			'application/octet-stream',
			'application/x-zip-compressed',
		];
	}

	/**
	 * Get File Extension
	 *
	 * Returns the file type's file extension
	 *
	 * @since 3.3.0
	 *
	 * @return string - file extension
	 */
	public function get_file_extension() {
		return 'zip';
	}

	/**
	 * Get File Property Name
	 *
	 * Get the property name to look for in the $_FILES superglobal
	 *
	 * @since 3.3.0
	 *
	 * @return string
	 */
	public function get_file_property_name() {
		return 'zip_upload';
	}

	/**
	 * Extract
	 *
	 * Performs the extraction of the zip files to a temporary directory.
	 * Returns an error if for some reason the ZipArchive utility isn't available.
	 * Otherwise, Returns an array containing the temporary extraction directory, and the list of extracted files.
	 *
	 * @since 3.3.0
	 *
	 * @return array|\WP_Error
	 */
	public function extract( $file_path, $allowed_file_types ) {
		if ( ! class_exists( '\ZipArchive' ) ) {
			return new \WP_Error( 'zip_error', 'PHP Zip extension not loaded' );
		}

		$zip = new \ZipArchive();

		$wp_upload_dir = wp_upload_dir();

		$temp_extraction_directory = $wp_upload_dir['basedir'] . '/elementor/tmp/' . uniqid();

		$zip->open( $file_path );

		// if an array of allowed file types is provided, get the filtered file list to extract.
		$allowed_files = $allowed_file_types ? $this->get_allowed_files( $zip, $allowed_file_types ) : null;

		$zip->extractTo( $temp_extraction_directory, $allowed_files );

		$zip->close();

		return [
			'temp_extraction_directory' => $temp_extraction_directory,
			'files' => $this->find_temp_files( $temp_extraction_directory ),
		];
	}

	/**
	 * Get Allowed Files
	 *
	 * Accepts a zipArchive instance and an array of allowed file types. Iterates over the zip archive's files and
	 * checks if their extensions are in the list of allowed file types. Returns an array containing all valid files.
	 * This method doesn't validate that the contents of the files (mime type) match the file extensions - that
	 * validation happens in the Uploads Manager once the files are extracted.
	 *
	 * @since 3.3.0
	 *
	 * @param \ZipArchive $zip
	 * @param array $allowed_file_types
	 * @return array
	 */
	private function get_allowed_files( $zip, $allowed_file_types ) {
		$allowed_files = [];

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$filename = $zip->getNameIndex( $i );
			$extension = pathinfo( $filename, PATHINFO_EXTENSION );

			if ( in_array( $extension, $allowed_file_types, true ) ) {
				$allowed_files[] = $filename;
			}
		}

		return $allowed_files;
	}

	/**
	 * Find temporary files.
	 *
	 * Recursively finds a list of temporary files from the extracted zip file.
	 *
	 * Example return data:
	 *
	 * [
	 *  0 => '/www/wp-content/uploads/elementor/tmp/5eb3a7a411d44/templates/block-2-col-marble-title.json',
	 *  1 => '/www/wp-content/uploads/elementor/tmp/5eb3a7a411d44/templates/block-2-col-text-and-photo.json',
	 * ]
	 *
	 * @since 2.9.8
	 * @access private
	 *
	 * @param string $temp_path - The temporary file path to scan for template files
	 *
	 * @return array An array of temporary files on the filesystem
	 */
	private function find_temp_files( $temp_path ) {
		$file_names = [];

		$possible_file_names = array_diff( scandir( $temp_path ), [ '.', '..' ] );

		// Find nested files in the unzipped path. This happens for example when the user imports a Template Kit.
		foreach ( $possible_file_names as $possible_file_name ) {
			$full_possible_file_name = $temp_path . '/' . $possible_file_name;
			if ( is_dir( $full_possible_file_name ) ) {
				$file_names = $file_names + $this->find_temp_files( $full_possible_file_name );
			} else {
				$file_names[] = [
					'name' => $full_possible_file_name,
					'type' => mime_content_type( $full_possible_file_name ),
				];
			}
		}

		return $file_names;
	}
}
