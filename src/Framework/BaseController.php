<?php

declare(strict_types=1);

namespace Framework;

use Framework\Core\Request;
use Framework\Core\Response;
use Framework\Interfaces\TemplateViewerInterface;
use Framework\Validation\DatabaseValidator;

/**
 * BaseController
 *
 * Common base class for all HTTP controllers.
 * Provides access to the current Request, Response, and view renderer.
 * Offers helper methods for HTML views, redirects, and JSON responses.
 *
 * Dependencies are injected by the Dispatcher via setter methods.
 * Concrete controllers extend this class and implement public action methods
 * that return a Response.
 */
abstract class BaseController
{
    protected Request $request;

    protected Response $response;

    protected TemplateViewerInterface $viewer;

    /**
     * @var callable Factory function that creates DatabaseValidator instances
     */
    protected $validatorFactory;

    /**
     * Inject the current Request.
     *
     * @param  Request  $request  The HTTP request instance
     */
    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    /**
     * Inject a Response instance to be used by this controller.
     *
     * @param  Response  $response  The HTTP response instance
     */
    public function setResponse(Response $response): void
    {
        $this->response = $response;
    }

    /**
     * Inject the template viewer/renderer.
     *
     * @param  TemplateViewerInterface  $viewer  The template rendering engine
     */
    public function setViewer(TemplateViewerInterface $viewer): void
    {
        $this->viewer = $viewer;
    }

    /**
     * Inject the validator factory.
     *
     * We use a factory closure so we can create validators with different data sets.
     *
     * @param  callable  $factory  Factory function that creates DatabaseValidator instances
     */
    public function setValidatorFactory(callable $factory): void
    {
        $this->validatorFactory = $factory;
    }

    /**
     * Create a validator instance with request data.
     *
     * We provide a fluent interface for validation in controllers.
     *
     * @param  array<string,mixed>  $data  Data to validate (defaults to all request data)
     */
    protected function validator(array $data = []): DatabaseValidator
    {
        $data = empty($data) ? $this->request->all() : $data;

        return ($this->validatorFactory)($data);
    }

    /**
     * Validate request data and return validator instance.
     *
     * We provide a convenience method that executes validation and returns the validator.
     *
     * @param  array<string, array<string>|string>  $rules  Validation rules
     * @param  array<string, string>  $messages  Custom error messages
     * @return DatabaseValidator The validator instance after validation runs
     */
    protected function validate(array $rules, array $messages = []): DatabaseValidator
    {
        $validator = $this->validator()
            ->rules($rules)
            ->messages($messages);

        $validator->passes(); // execute validation

        return $validator;
    }

    /**
     * Convenience accessor for the current Request.
     */
    protected function request(): Request
    {
        return $this->request;
    }

    /**
     * Convenience accessor for the Response.
     */
    protected function response(): Response
    {
        return $this->response;
    }

    /**
     * Convenience accessor for the template viewer.
     */
    protected function viewer(): TemplateViewerInterface
    {
        return $this->viewer;
    }

    /**
     * Render an HTML view into the Response.
     *
     * We set the Content-Type header and delegate rendering to the template viewer.
     * The TemplateViewerInterface handles escaping to prevent XSS.
     *
     * @param  string|array<string,mixed>|null  $template  Template name or data array
     * @param  array<string,mixed>  $data  Template variables
     */
    public function view(string|array|null $template = null, array $data = []): Response
    {
        if (is_array($template)) {
            $data = $template;
            $template = null;
        }

        // Ensure HTML content type for browser responses
        $this->response->addHeader('Content-Type', 'text/html; charset=utf-8');

        $body = $this->viewer->render($template, $data);
        $this->response->setBody($body);

        return $this->response;
    }

    /**
     * Redirect to a given URL.
     *
     * We use Response::redirect(), which normalizes localized URLs
     * and sets the Location header with the appropriate status code.
     *
     * @param  string  $url  Target URL
     * @param  int  $status  HTTP redirect status code (default 302 Found)
     */
    public function redirect(string $url, int $status = 302): Response
    {
        $this->response->redirect($url, $status);

        return $this->response;
    }

    /**
     * Return a JSON response.
     *
     * We use Response::setJson() to set Content-Type and encode payload.
     *
     * @param  array<string,mixed>  $data  Data to encode as JSON
     * @param  int  $status  HTTP status code (default 200 OK)
     *
     * @throws \JsonException If JSON encoding fails
     */
    public function json(array $data, int $status = 200): Response
    {
        $this->response->setStatusCode($status);
        $this->response->setJson($data);

        return $this->response;
    }

    /**
     * Return a JSON success response with standardized format.
     *
     * We wrap the data in a consistent success envelope for API clients.
     *
     * @param  mixed  $data  The payload to return
     * @param  int  $statusCode  HTTP status code (default 200 OK)
     *
     * @throws \JsonException If JSON encoding fails
     */
    public function jsonSuccess(mixed $data, int $statusCode = 200): Response
    {
        return $this->json([
            'success' => true,
            'data' => $data,
        ], $statusCode);
    }

    /**
     * Return a JSON error response with standardized format.
     *
     * We wrap the error in a consistent envelope for API clients.
     *
     * @param  string  $message  Error message to return
     * @param  int  $statusCode  HTTP status code (default 400 Bad Request)
     *
     * @throws \JsonException If JSON encoding fails
     */
    public function jsonError(string $message, int $statusCode = 400): Response
    {
        return $this->json([
            'success' => false,
            'error' => $message,
        ], $statusCode);
    }
}
