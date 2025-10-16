<?php namespace Seiger\sCommerce\Interfaces;

/**
 * @deprecated
 * IntegrationInterface - Contract for sCommerce integrations
 *
 * This interface defines the standard contract that all sCommerce integrations
 * must implement. It ensures consistency across different integration types
 * and provides a unified API for integration management, configuration,
 * and user interface components.
 *
 * Key Responsibilities:
 * - Integration identification and metadata (key, title, icon)
 * - Settings management and validation
 * - User interface components (widgets, fields)
 * - Data validation and preparation
 * - Configuration field definitions
 *
 * Integration Lifecycle:
 * 1. Integration registration with unique key and metadata
 * 2. Settings configuration through defineFields() and prepareSettings()
 * 3. Widget rendering for user interface display
 * 4. Validation of settings and data through getValidationRules()
 * 5. Settings retrieval and management through getSettings()
 *
 * All concrete integration classes must implement this interface to ensure
 * compatibility with the sCommerce integration system and provide consistent
 * behavior across different integration types.
 *
 * @package Seiger\sCommerce\Interfaces
 * @author Seiger IT Team
 * @since 1.0.0
 */
interface IntegrationInterface
{
    /**
     * Get the unique identifier key for the integration.
     *
     * This method returns a unique string identifier that distinguishes this
     * integration from all others in the system. The key is used for:
     * - Database lookups and storage
     * - URL routing and API endpoints
     * - Integration resolution and instantiation
     * - Configuration and settings management
     *
     * The key should be:
     * - Unique across all integrations
     * - URL-safe (lowercase, alphanumeric, hyphens allowed)
     * - Descriptive of the integration's purpose
     * - Consistent and immutable
     *
     * @return string The unique identifier key for the integration
     */
    public function getKey(): string;

    /**
     * Get the admin display icon for the integration.
     *
     * This method returns an HTML string containing the icon that represents
     * this integration in the administrative interface. The icon is used for:
     * - Visual identification in integration lists
     * - User interface elements and buttons
     * - Navigation and menu items
     * - Status indicators and badges
     *
     * The returned string should contain:
     * - Valid HTML markup (typically <i> tags with CSS classes)
     * - Font Awesome or similar icon library classes
     * - Appropriate styling for the admin theme
     * - Consistent sizing and appearance
     *
     * @return string HTML string containing the integration icon
     */
    public function getIcon(): string;

    /**
     * Get the human-readable title for the integration.
     *
     * This method returns a localized, human-readable title that describes
     * the integration's purpose and functionality. The title is used for:
     * - Display in administrative interfaces
     * - User documentation and help text
     * - Integration selection and configuration
     * - Error messages and notifications
     *
     * The title should be:
     * - Descriptive and clear about the integration's purpose
     * - Localized for the current language
     * - Consistent with the integration's functionality
     * - User-friendly and professional
     *
     * @return string The human-readable title of the integration
     */
    public function getTitle(): string;

    /**
     * Get validation rules for the integration settings and data.
     *
     * This method defines comprehensive validation rules for all fields related
     * to this integration. The rules ensure data integrity, proper formatting,
     * and compliance with integration requirements.
     *
     * The validation rules should cover:
     * - Required fields and their constraints
     * - Data type validation (string, integer, boolean, etc.)
     * - Format validation (email, URL, date, etc.)
     * - Business logic validation (ranges, dependencies, etc.)
     * - Security validation (sanitization, injection prevention)
     *
     * The returned array should follow Laravel validation rule format:
     * - Field name as key
     * - Validation rules as pipe-separated string or array
     * - Custom error messages if needed
     *
     * @return array Associative array of validation rules where keys are field names
     *               and values are validation rule strings or arrays
     */
    public function getValidationRules(): array;

    /**
     * Render the integration widget for the administrative interface.
     *
     * This method generates the HTML content for the integration's main widget
     * that is displayed in the administrative interface. The widget typically
     * contains the integration's primary functionality, controls, and status
     * information.
     *
     * The widget should include:
     * - Integration status and configuration
     * - Action buttons and controls
     * - Progress indicators and logs
     * - Settings and options interface
     * - Error messages and notifications
     *
     * The returned HTML should be:
     * - Well-formed and valid HTML
     * - Styled appropriately for the admin theme
     * - Responsive and accessible
     * - Interactive with proper JavaScript integration
     *
     * @return string HTML content for the integration widget
     */
    public function renderWidget(): string;

    /**
     * Define the configuration fields for this integration.
     *
     * This method specifies the configurable fields that appear in the
     * administrative interface for this integration. These fields allow
     * users to customize the integration's behavior, settings, and
     * connection parameters.
     *
     * The field definitions should include:
     * - Field types (text, select, checkbox, textarea, etc.)
     * - Field labels and descriptions
     * - Default values and options
     * - Validation rules and constraints
     * - Help text and tooltips
     * - Conditional display logic
     *
     * The returned array should be structured as:
     * - Nested arrays for grouped fields (sections/tabs)
     * - Field definitions with type, label, options, etc.
     * - Proper field naming conventions
     * - Consistent with admin theme styling
     *
     * @return array Array of field definitions, optionally grouped by sections
     */
    public function defineFields(): array;

    /**
     * Validate and prepare the settings data for database storage.
     *
     * This method processes incoming settings data by validating it against
     * the integration's validation rules, sanitizing input, and formatting
     * it as a JSON string suitable for database storage.
     *
     * The preparation process includes:
     * - Validation against getValidationRules()
     * - Data sanitization and cleaning
     * - Type conversion and formatting
     * - Default value application
     * - Security validation and filtering
     * - JSON encoding with proper escaping
     *
     * The method should handle:
     * - Required field validation
     * - Data type conversion
     * - Format validation (email, URL, etc.)
     * - Business logic validation
     * - Security sanitization
     *
     * @param array $data The raw input settings data to process
     * @return string JSON-encoded string containing validated and prepared settings
     * @throws \Illuminate\Validation\ValidationException If validation rules are not met
     * @throws \InvalidArgumentException If data format is invalid
     */
    public function prepareSettings(array $data): string;

    /**
     * Retrieve the current settings for the integration.
     *
     * This method fetches the stored configuration settings for this integration
     * from the database and returns them as an associative array. The settings
     * include all configurable options, connection parameters, and behavioral
     * preferences that have been set by the user.
     *
     * The returned settings should include:
     * - All user-configured options
     * - Default values for unset options
     * - Properly typed data (strings, booleans, arrays, etc.)
     * - Sanitized and safe values
     * - Current integration state information
     *
     * The method should handle:
     * - Database retrieval and JSON decoding
     * - Default value application
     * - Data type conversion
     * - Error handling for corrupted data
     * - Fallback to sensible defaults
     *
     * @return array Associative array containing all integration settings
     */
    public function getSettings(): array;
}
