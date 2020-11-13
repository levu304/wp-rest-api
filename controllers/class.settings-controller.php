<?php

class Settings_Controller {
    public function get_languages($request) {
          try {
            $parsed_args = wp_parse_args(
                array(),
                array(
                    'id'                          => 'locale',
                    'name'                        => 'locale',
                    'languages'                   => array(),
                    'translations'                => array(),
                    'selected'                    => '',
                    'echo'                        => 1,
                    'show_available_translations' => true,
                    'show_option_site_default'    => false,
                    'show_option_en_us'           => true,
                )
            );
            // Bail if no ID or no name.
            if ( ! $parsed_args['id'] || ! $parsed_args['name'] ) {
                return;
            }

            // English (United States) uses an empty string for the value attribute.
            if ( 'en_US' === $parsed_args['selected'] ) {
                $parsed_args['selected'] = '';
            }

            $translations = $parsed_args['translations'];
            if ( empty( $translations ) ) {
                require_once( ABSPATH . 'wp-admin/includes/translation-install.php' );
                $translations = wp_get_available_translations();
            }

            /*
            * $parsed_args['languages'] should only contain the locales. Find the locale in
            * $translations to get the native name. Fall back to locale.
            */
            $languages = array();
            foreach ( $parsed_args['languages'] as $locale ) {
                if ( isset( $translations[ $locale ] ) ) {
                    $translation = $translations[ $locale ];
                    $languages[] = array(
                        'language'    => $translation['language'],
                        'native_name' => $translation['native_name'],
                        'lang'        => current( $translation['iso'] ),
                    );

                    // Remove installed language from available translations.
                    unset( $translations[ $locale ] );
                } else {
                    $languages[] = array(
                        'language'    => $locale,
                        'native_name' => $locale,
                        'lang'        => '',
                    );
                }
            }

            if ( $parsed_args['show_option_en_us'] ) {
                 $languages[] = array(
                    'language'    => 'en_US',
                    'native_name' => 'English (United States)',
                    'lang'        => 'en',
                );
            }

            $translations_available = ( ! empty( $translations ) && $parsed_args['show_available_translations'] );

            if ( $translations_available ) { 
                foreach ( $translations as $translation ) {
                    $languages[] = array(
                        'language'    => $translation['language'],
                        'native_name' => $translation['native_name'],
                        'lang'        => current( $translation['iso'] ),
                    );
                }
            }

            return $languages;
        } catch (\Exception $ex) {
            return wp_send_json_error(
                array(
                    'message' => $ex->getMessage()
                ),
                500
            );
        } 
    }
}