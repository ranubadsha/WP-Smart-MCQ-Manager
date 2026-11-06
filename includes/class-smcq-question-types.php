<?php
/** Question types and deterministic, backwards-compatible answer validation. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class SMCQ_Question_Types {
	public static function types() {
		return array( 'single' => 'Single correct answer', 'multiple' => 'Multiple correct answers', 'true_false' => 'True / False', 'passage' => 'Passage-based MCQ', 'image' => 'Image-based MCQ', 'audio' => 'Audio-based MCQ', 'math' => 'Mathematics / formula question' );
	}
	public static function type( $value ) {
		return is_string( $value ) && isset( self::types()[ $value ] ) ? $value : 'single';
	}
	public static function keys( $type ) {
		return 'true_false' === self::type( $type ) ? array( 'A', 'B' ) : array( 'A', 'B', 'C', 'D' );
	}
	public static function canonical( $value, $type, $options ) {
		if ( ! is_string( $value ) || ! is_array( $options ) ) { return null; }
		$allowed = self::keys( $type );
		if ( 'multiple' === $type ) {
			$keys = explode( ',', $value );
			if ( count( $keys ) < 1 || count( $keys ) > count( $allowed ) ) { return null; }
			foreach ( $keys as $key ) {
				if ( ! in_array( $key, $allowed, true ) || ! isset( $options[ $key ] ) || ! is_string( $options[ $key ] ) || '' === trim( $options[ $key ] ) ) { return null; }
			}
			if ( count( array_unique( $keys ) ) !== count( $keys ) ) { return null; }
			sort( $keys, SORT_STRING );
			return implode( ',', $keys );
		}
		return in_array( $value, $allowed, true ) && isset( $options[ $value ] ) && is_string( $options[ $value ] ) && '' !== trim( $options[ $value ] ) ? $value : null;
	}
	public static function ready( $type, $options, $correct, $passage = '', $media = '' ) {
		$type = self::type( $type );
		if ( ! is_array( $options ) ) { return false; }
		foreach ( self::keys( $type ) as $key ) {
			if ( ! isset( $options[ $key ] ) || ! is_string( $options[ $key ] ) || '' === trim( $options[ $key ] ) ) { return false; }
		}
		if ( 'multiple' === $type && ( ! is_string( $correct ) || count( explode( ',', $correct ) ) < 2 ) ) { return false; }
		if ( 'passage' === $type && '' === trim( (string) $passage ) ) { return false; }
		if ( in_array( $type, array( 'image', 'audio' ), true ) && '' === trim( (string) $media ) ) { return false; }
		return null !== self::canonical( $correct, $type, $options );
	}
	public static function correct_from_meta( $id ) {
		$type = self::type( get_post_meta( $id, '_smcq_type', true ) );
		if ( 'multiple' !== $type ) { return (string) get_post_meta( $id, '_smcq_correct', true ); }
		$stored = get_post_meta( $id, '_smcq_correct_keys', true );
		if ( ! is_array( $stored ) ) { return ''; }
		$keys = array_values( array_intersect( array( 'A', 'B', 'C', 'D' ), $stored ) );
		return implode( ',', $keys );
	}
	public static function media_url( $id, $type ) {
		if ( ! in_array( $type, array( 'image', 'audio' ), true ) ) { return ''; }
		$id = absint( $id );
		if ( ! $id || 'attachment' !== get_post_type( $id ) ) { return ''; }
		$mime = (string) get_post_mime_type( $id );
		if ( 0 !== strpos( $mime, 'image/' ) && 'image' === $type ) { return ''; }
		if ( 0 !== strpos( $mime, 'audio/' ) && 'audio' === $type ) { return ''; }
		return esc_url_raw( (string) wp_get_attachment_url( $id ) );
	}
}