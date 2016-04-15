<?php

class WPML_ST_String_Factory extends WPML_WPDB_User {

	/** @var int[] $string_id_cache */
	protected $string_id_cache = array();

	/**
	 * @param string $string
	 * @param string $context
	 * @param bool|false $name
	 *
	 * @return mixed
	 */
	public function get_string_id( $string, $context, $name = false ) {
		$sql          = "SELECT id FROM {$this->wpdb->prefix}icl_strings WHERE value=%s AND context=%s";
		$prepare_args = array( $string, $context );
		if ( $name !== false ) {
			$sql .= " AND name = %s ";
			$prepare_args[] = $name;
		}
		$sql                                 = $this->wpdb->prepare( $sql . " LIMIT 1", $prepare_args );
		$cache_key                           = md5( $sql );
		$this->string_id_cache[ $cache_key ] = isset( $this->string_id_cache[ $cache_key ] )
			? $this->string_id_cache[ $cache_key ]
			: (int) $this->wpdb->get_var( $sql );

		return $this->string_id_cache[ $cache_key ];
	}
}