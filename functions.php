<?php
// var functions
if( ! function_exists('print_rr') ) {
	function print_rr( $content = "", $subject = NULL ) {
		if( $subject )
			echo '<strong>' . $subject . '</strong><br>'; 
		
		echo "<pre>";
		print_r( $content );
		echo "</pre>";
	}
} 

if ( ! function_exists( 'siget' ) ) {
	function siget( $name, $array = null ) {
		if ( ! isset( $array ) ) {
			$array = $_GET;
		}

		if ( isset( $array[ $name ] ) ) {
			return $array[ $name ];
		}

		return '';
	}
}

if ( ! function_exists( 'sipost' ) ) {
	function sipost( $name, $do_stripslashes = true ) {
		if ( isset( $_POST[ $name ] ) ) {
			return $do_stripslashes && function_exists( 'stripslashes_deep' ) ? stripslashes_deep( $_POST[ $name ] ) : $_POST[ $name ];
		}

		return '';
	}
}

if ( ! function_exists( 'siar' ) ) {
	function siar( $array, $name ) {
		if ( isset( $array[ $name ] ) ) {
			return $array[ $name ];
		}

		return '';
	}
}

if ( ! function_exists( 'siars' ) ) {
	function siars( $array, $name ) {
		$names = explode( '/', $name );
		$val   = $array;
		foreach ( $names as $current_name ) {
			$val = siar( $val, $current_name );
		}

		return $val;
	}
}

if ( ! function_exists( 'siempty' ) ) {
	function siempty( $name, $array = null ) {

		if ( is_array( $name ) ) {
			return empty( $name );
		}

		if ( ! $array ) {
			$array = $_POST;
		}

		$val = siar( $array, $name );

		return empty( $val );
	}
}

if ( ! function_exists( 'siblank' ) ) {
	function siblank( $text ) {
		return empty( $text ) && strval( $text ) != '0';
	}
}

if ( ! function_exists( 'siobj' ) ) {
	function siobj( $obj, $name ) {
		if ( isset( $obj->$name ) ) {
			return $obj->$name;
		}

		return '';
	}
}
if ( ! function_exists( 'siexplode' ) ) {
	function siexplode( $sep, $string, $count ) {
		$ary = explode( $sep, $string );
		while ( count( $ary ) < $count ) {
			$ary[] = '';
		}

		return $ary;
	}
}

if ( ! function_exists( 'get_client_ip' ) ) {
	function get_client_ip() {
		$ipaddress	= '';
		
		if ( isset( $_SERVER['HTTP_CLIENT_IP'] ) )
			$ipaddress = $_SERVER['HTTP_CLIENT_IP'];
		else if( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) )
			$ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
		else if( isset( $_SERVER['HTTP_X_FORWARDED'] ) )
			$ipaddress = $_SERVER['HTTP_X_FORWARDED'];
		else if( isset( $_SERVER['HTTP_FORWARDED_FOR'] ) )
			$ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
		else if( isset( $_SERVER['HTTP_FORWARDED'] ) )
			$ipaddress = $_SERVER['HTTP_FORWARDED'];
		else if( isset( $_SERVER['REMOTE_ADDR'] ) )
			$ipaddress = $_SERVER['REMOTE_ADDR'];
		else
			$ipaddress = 'UNKNOWN';
			
		return $ipaddress;
	}
}

if ( ! function_exists( 'get_ip' ) ) {
	function get_ip() {
		$ip	= '';
		
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {   				//check ip from share internet		
		  	$ip	= $_SERVER['HTTP_CLIENT_IP'];
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {   	//to check ip is pass from proxy		
		  	$ip	= $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else {
		  	$ip	= $_SERVER['REMOTE_ADDR'];
		}
		
		return $ip;
	}
}