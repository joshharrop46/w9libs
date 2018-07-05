<?php
/**
 * Copyright (C) 2014-2017 ServMask Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * ███████╗███████╗██████╗ ██╗   ██╗███╗   ███╗ █████╗ ███████╗██╗  ██╗
 * ██╔════╝██╔════╝██╔══██╗██║   ██║████╗ ████║██╔══██╗██╔════╝██║ ██╔╝
 * ███████╗█████╗  ██████╔╝██║   ██║██╔████╔██║███████║███████╗█████╔╝
 * ╚════██║██╔══╝  ██╔══██╗╚██╗ ██╔╝██║╚██╔╝██║██╔══██║╚════██║██╔═██╗
 * ███████║███████╗██║  ██║ ╚████╔╝ ██║ ╚═╝ ██║██║  ██║███████║██║  ██╗
 * ╚══════╝╚══════╝╚═╝  ╚═╝  ╚═══╝  ╚═╝     ╚═╝╚═╝  ╚═╝╚══════╝╚═╝  ╚═╝
 */

class Ai1wm_Compressor extends Ai1wm_Archiver {

	/**
	 * Overloaded constructor that opens the passed file for writing
	 *
	 * @param string $file_name File to use as archive
	 */
	public function __construct( $file_name ) {
		// Call parent, to initialize variables
		parent::__construct( $file_name, true );
	}

	/**
	 * Add a file to the archive
	 *
	 * @param string $file_name     File to add to the archive
	 * @param string $new_file_name Write the file with a different name
	 * @param int    $file_written  File written (in bytes)
	 * @param int    $file_offset   File offset (in bytes)
	 * @param int    $timeout       Process timeout (in seconds)
	 *
	 * @throws \Ai1wm_Not_Seekable_Exception
	 * @throws \Ai1wm_Not_Writable_Exception
	 * @throws \Ai1wm_Quota_Exceeded_Exception
	 *
	 * @return bool
	 */
	public function add_file( $file_name, $new_file_name = '', &$file_written = 0, &$file_offset = 0, $timeout = 0 ) {
		$file_written = 0;

		// Replace / with DIRECTORY_SEPARATOR in file name
		$file_name = str_replace( '/', DIRECTORY_SEPARATOR, $file_name );

		// Replace \ with \\ in file name (Windows)
		$file_name = str_replace( '\\', '\\\\', $file_name );

		// Flag to hold if file data has been processed
		$completed = true;

		// Start time
		$start = microtime( true );

		// Open the file for reading in binary mode
		if ( ( $file_handle = @fopen( $file_name, 'rb' ) ) !== false ) {
			$file_bytes = 0;

			// Get header block
			if ( ( $block = $this->get_file_block( $file_name, $new_file_name ) ) ) {

				// Write header block
				if ( $file_offset === 0 ) {
					if ( ( $file_bytes = @fwrite( $this->file_handle, $block ) ) !== false ) {
						if ( strlen( $block ) !== $file_bytes ) {
							throw new Ai1wm_Quota_Exceeded_Exception( sprintf( 'Out of disk space. Unable to write header to file. File: %s', $this->file_name ) );
						}
					} else {
						throw new Ai1wm_Not_Writable_Exception( sprintf( 'Unable to write header to file. File: %s', $this->file_name ) );
					}
				}

				// Set file offset
				if ( @fseek( $file_handle, $file_offset, SEEK_SET ) !== -1 ) {

					// Read the file in 512KB chunks
					while ( false === @feof( $file_handle ) ) {

						// Read the file in chunks of 512KB
						if ( ( $file_content = @fread( $file_handle, 512000 ) ) !== false ) {
							if ( ( $file_bytes = @fwrite( $this->file_handle, $file_content ) ) !== false ) {
								if ( strlen( $file_content ) !== $file_bytes ) {
									throw new Ai1wm_Quota_Exceeded_Exception( sprintf( 'Out of disk space. Unable to write content to file. File: %s', $this->file_name ) );
								}
							} else {
								throw new Ai1wm_Not_Writable_Exception( sprintf( 'Unable to write content to file. File: %s', $this->file_name ) );
							}

							// Set file written
							$file_written += $file_bytes;
						}

						// Time elapsed
						if ( $timeout ) {
							if ( ( microtime( true ) - $start ) > $timeout ) {
								$completed = false;
								break;
							}
						}
					}
				}

				// Set file offset
				$file_offset += $file_written;

				// Write file size to file header
				if ( ( $block = $this->get_file_size_block( $file_offset ) ) ) {

					// Seek to beginning of file size
					if ( @fseek( $this->file_handle, - $file_offset - 4096 - 12 - 14, SEEK_CUR ) === -1 ) {
						throw new Ai1wm_Not_Seekable_Exception( sprintf( 'Unable to seek to offset on file. File: %s Offset: %d', $this->file_name, - $file_offset - 4096 - 12 - 14 ) );
					}

					// Write file size to file header
					if ( ( $file_bytes = @fwrite( $this->file_handle, $block ) ) !== false ) {
						if ( strlen( $block ) !== $file_bytes ) {
							throw new Ai1wm_Quota_Exceeded_Exception( sprintf( 'Out of disk space. Unable to write size to file. File: %s', $this->file_name ) );
						}
					} else {
						throw new Ai1wm_Not_Writable_Exception( sprintf( 'Unable to write size to file. File: %s', $this->file_name ) );
					}

					// Seek to end of file content
					if ( @fseek( $this->file_handle, + $file_offset + 4096 + 12, SEEK_CUR ) === -1 ) {
						throw new Ai1wm_Not_Seekable_Exception( sprintf( 'Unable to seek to offset on file. File: %s Offset: %d', $this->file_name, + $file_offset + 4096 + 12 ) );
					}
				}
			}

			// Close the handle
			@fclose( $file_handle );
		}

		return $completed;
	}

	/**
	 * Generate binary block header for a file
	 *
	 * @param string $file_name     Filename to generate block header for
	 * @param string $new_file_name Write the file with a different name
	 *
	 * @return mixed
	 */
	private function get_file_block( $file_name, $new_file_name = '' ) {
		$block = false;

		// Get stats about the file
		if ( ( $stat = @stat( $file_name ) ) !== false ) {

			// Get path details
			if ( empty( $new_file_name ) ) {
				$pathinfo = pathinfo( $file_name );
			} else {
				$pathinfo = pathinfo( $new_file_name );
			}

			// Filename of the file we are accessing
			$name = $pathinfo['basename'];

			// Size in bytes of the file
			$size = $stat['size'];

			// Last time the file was modified
			$date = $stat['mtime'];

			// Replace DIRECTORY_SEPARATOR with / in path, we want to always have /
			$path = str_replace( DIRECTORY_SEPARATOR, '/', $pathinfo['dirname'] );

			// Concatenate block format parts
			$format = implode( '', $this->block_format );

			// Pack file data into binary string
			$block = pack( $format, $name, $size, $date, $path );
		}

		return $block;
	}

	/**
	 * Generate file size binary block header for a file
	 *
	 * @param int $file_size File size
	 *
	 * @return string
	 */
	public function get_file_size_block( $file_size ) {
		$block = false;

		// Pack file data into binary string
		if ( isset( $this->block_format[1] ) ) {
			$block = pack( $this->block_format[1], $file_size );
		}

		return $block;
	}
}
