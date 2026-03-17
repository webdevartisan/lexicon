<?php

declare(strict_types=1);

namespace Framework\Http\Middleware;

use Framework\Core\Container;
use Framework\Core\Request;
use Framework\Core\Response;
use Framework\Interfaces\MiddlewareInterface;
use Framework\Interfaces\RequestHandlerInterface;

/**
 * Container debug middleware for tracking dependency injection activity.
 *
 * Enables container debug mode and appends a visual debug toolbar to HTML
 * responses when APP_DEBUG is enabled. Shows instantiation counts, resolution
 * methods, and performance metrics to identify optimization opportunities.
 */
class ContainerDebugMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Container $container
    ) {}

    /**
     * Handle the request and inject debug toolbar.
     */
    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        if ($_ENV['APP_DEBUG'] == 'false') {
            // If not in debug mode, just pass through
            return $next->handle($request);
        }

        // Enable debug tracking
        $this->container->enableDebug();
        
        // Process request
        $response =  $next->handle($request);
        
        // Only inject toolbar for HTML responses in debug mode
        if ($this->shouldInjectToolbar($response)) {
            $this->injectDebugToolbar($response);
        }
        
        return $response;
    }

    /**
     * Check if debug toolbar should be injected.
     */
    private function shouldInjectToolbar(Response $response): bool
    {
        // Only in debug mode
        if (!($_ENV['APP_DEBUG'] ?? false)) {
            return false;
        }

        // Only for HTML responses
        $contentType = $response->getHeader('Content-Type') ?? '';
        if (stripos($contentType, 'text/html') === false && empty($contentType)) {
            // Assume HTML if no content type set
            $body = $response->getBody();
            return stripos($body, '<html') !== false || stripos($body, '<!DOCTYPE') !== false;
        }

        return stripos($contentType, 'text/html') !== false;
    }

    /**
     * Inject debug toolbar HTML into response.
     */
    private function injectDebugToolbar(Response $response): void
    {
        $report = $this->container->getDebugReport();

        $toolbar = $this->renderToolbar($report);
        
        $body = $response->getBody();
        
        // Try to inject before </body>, otherwise append
        if (stripos($body, '</body>') !== false) {
            $body = str_ireplace('</body>', $toolbar . '</body>', $body);
        } else {
            $body .= $toolbar;
        }
        
        $response->setBody($body);
    }

    /**
     * Render the debug toolbar HTML.
     */
    private function renderToolbar(array $report): string
    {
        $html = $this->getToolbarStyles();
        $html .= '<div id="container-debug-toolbar">';
        $html .= '<div class="debug-toolbar-toggle" onclick="this.parentElement.classList.toggle(\'expanded\')">📦 Container Debug</div>';
        $html .= '<div class="debug-toolbar-content">';
        
        // Summary stats
        $html .= '<div class="debug-stats">';
        $html .= '<div class="stat"><strong>Registered:</strong> ' . $report['total_registered'] . '</div>';
        $html .= '<div class="stat"><strong>Resolutions:</strong> ' . $report['total_resolutions'] . '</div>';
        $html .= '<div class="stat"><strong>Duration:</strong> ' . number_format($report['total_duration'], 2) . 'ms</div>';
        $html .= '<div class="stat"><strong>Memory:</strong> ' . $this->formatBytes($report['total_memory']) . '</div>';
        $html .= '</div>';
        
        // Resolution table
        $html .= '<table class="debug-table">';
        $html .= '<thead><tr><th>Class</th><th>Count</th><th>Method</th><th>Type</th><th>Time</th><th>Memory</th></tr></thead>';
        $html .= '<tbody>';
        
        foreach ($report['resolutions'] as $class => $data) {
            $shortClass = $this->getShortClassName($class);
            $rowClass = $data['count'] > 1 ? 'warning' : '';
            $typeClass = $data['type'] === 'singleton' ? 'singleton' : 'factory';
            
            $html .= "<tr class='{$rowClass}'>";
            $html .= "<td title='{$class}'>{$shortClass}</td>";
            $html .= "<td>{$data['count']}</td>";
            $html .= "<td>{$data['method']}</td>";
            $html .= "<td class='{$typeClass}'>{$data['type']}</td>";
            $html .= "<td>" . number_format($data['total_duration'], 2) . "ms</td>";
            $html .= "<td>" . $this->formatBytes($data['total_memory']) . "</td>";
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        $html .= '</div></div>';
        
        return $html;
    }

    /**
     * Get CSS styles for the toolbar.
     */
    private function getToolbarStyles(): string
    {
        return <<<'HTML'
<style>
#container-debug-toolbar {
    position: fixed;
    bottom: 0;
    right: 0;
    width: 400px;
    max-height: 60vh;
    background: #1e1e1e;
    color: #d4d4d4;
    font-family: 'Consolas', 'Monaco', monospace;
    font-size: 12px;
    border: 1px solid #333;
    border-bottom: none;
    border-right: none;
    box-shadow: -2px -2px 10px rgba(0,0,0,0.3);
    z-index: 999999;
    overflow: hidden;
}
#container-debug-toolbar.expanded {
    width: 90%;
    max-width: 1200px;
    max-height: 80vh;
}
.debug-toolbar-toggle {
    background: #007acc;
    color: white;
    padding: 8px 12px;
    cursor: pointer;
    font-weight: bold;
    user-select: none;
}
.debug-toolbar-toggle:hover {
    background: #005a9e;
}
.debug-toolbar-content {
    display: none;
    padding: 12px;
    max-height: calc(80vh - 40px);
    overflow-y: auto;
}
#container-debug-toolbar.expanded .debug-toolbar-content {
    display: block;
}
.debug-stats {
    display: flex;
    gap: 20px;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #333;
}
.debug-stats .stat {
    flex: 1;
}
.debug-stats strong {
    color: #4ec9b0;
}
.debug-table {
    width: 100%;
    border-collapse: collapse;
}
.debug-table th {
    background: #252526;
    padding: 8px;
    text-align: left;
    border-bottom: 2px solid #007acc;
    color: #4ec9b0;
    position: sticky;
    top: 0;
}
.debug-table td {
    padding: 6px 8px;
    border-bottom: 1px solid #333;
}
.debug-table tr:hover {
    background: #2d2d30;
}
.debug-table tr.warning {
    background: #3a2a1a;
}
.debug-table tr.warning:hover {
    background: #4a3a2a;
}
.debug-table .singleton {
    color: #4ec9b0;
    font-weight: bold;
}
.debug-table .factory {
    color: #dcdcaa;
}
</style>
HTML;
    }

    /**
     * Get short class name from FQCN.
     */
    private function getShortClassName(string $class): string
    {
        $parts = explode('\\', $class);
        return end($parts);
    }

    /**
     * Format bytes to human readable.
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . 'B';
        } elseif ($bytes < 1048576) {
            return round($bytes / 1024, 2) . 'KB';
        } else {
            return round($bytes / 1048576, 2) . 'MB';
        }
    }
}
