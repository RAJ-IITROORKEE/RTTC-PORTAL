<?php
/**
 * Helpers for personal registration details.
 */
if (!defined('APP_INIT')) die('Direct access not permitted');

if (!class_exists('RegistrationHelper', false)) {
    class RegistrationHelper
    {
        /**
         * EWS is available only for General applicants; PWD is category-independent.
         */
        public static function normalizeSpecialCategories(
            string $category,
            bool $ewsSelected,
            bool $pwdSelected
        ): array {
            return [
                'ews' => $category === 'General' && $ewsSelected ? 1 : 0,
                'pwd' => $pwdSelected ? 1 : 0,
            ];
        }
    }
}
