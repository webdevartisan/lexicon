<?php

namespace App\Services;

use App\Mail\Mailable;
use Exception;

/**
 * Email Template Registry Service
 *
 * provide a centralized registry for discovering and instantiating
 * email templates with sample data for testing and preview purposes.
 */
class EmailTemplateRegistry
{
    /**
     * Get all available email templates with metadata.
     *
     * register each template with sample data needed for instantiation,
     * making it easy to test templates with realistic content.
     *
     * @return array<string, array>
     */
    public function getAll(): array
    {
        // register each template with sample data for testing
        return [
            'welcome' => [
                'name' => 'Welcome Email',
                'description' => 'Sent to new users after registration',
                'class' => 'App\\Mail\\WelcomeEmail',
                'sample_data' => [
                    'user' => [
                        'first_name' => 'John',
                        'username' => 'johndoe',
                        'email' => 'john@example.com',
                    ],
                ],
            ],
            'password_reset' => [
                'name' => 'Password Reset',
                'description' => 'Sent when user requests password reset',
                'class' => 'App\\Mail\\PasswordResetEmail',
                'sample_data' => [
                    'user' => [
                        'first_name' => 'Jane',
                        'email' => 'jane@example.com',
                    ],
                    'reset_url' => 'https://yourdomain.com/password/reset/SAMPLE_TOKEN_HERE',
                    'expires_in' => 60,
                ],
            ],
        ];
    }

    /**
     * Get metadata for a specific template.
     *
     * @param  string  $templateKey  Template identifier
     * @return array|null Template data or null if not found
     */
    public function get(string $templateKey): ?array
    {
        $templates = $this->getAll();

        return $templates[$templateKey] ?? null;
    }

    /**
     * Instantiate email template with sample data.
     *
     * use reflection to create instances of Mailable classes,
     * injecting sample data for preview and testing purposes.
     *
     * @param  string  $templateKey  Template identifier
     * @return Mailable Instantiated email template
     *
     * @throws Exception If template not found or instantiation fails
     */
    public function instantiate(string $templateKey): Mailable
    {
        $template = $this->get($templateKey);

        if (!$template) {
            throw new Exception("Email template '{$templateKey}' not found");
        }

        $className = $template['class'];

        if (!class_exists($className)) {
            throw new Exception("Email class '{$className}' does not exist");
        }

        try {
            $data = $template['sample_data'];

            // use reflection to determine constructor parameters
            $reflection = new \ReflectionClass($className);
            $constructor = $reflection->getConstructor();

            if (!$constructor) {
                return new $className();
            }

            // map sample data to constructor parameters
            $params = [];
            foreach ($constructor->getParameters() as $param) {
                $paramName = $param->getName();

                if (isset($data[$paramName])) {
                    $params[] = $data[$paramName];
                } elseif ($param->isDefaultValueAvailable()) {
                    $params[] = $param->getDefaultValue();
                } else {
                    throw new Exception("Missing required parameter '{$paramName}' for {$className}");
                }
            }

            return $reflection->newInstanceArgs($params);

        } catch (Exception $e) {
            error_log("Failed to instantiate email template '{$templateKey}': ".$e->getMessage());
            throw new Exception('Failed to create email template: '.$e->getMessage());
        }
    }
}
