/**
 * Error Display Component
 * 
 * Reusable React component for displaying errors with consistent styling.
 * Provides helpful error messages and recovery options.
 */

import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { Button, Notice } from '@wordpress/components';

const ErrorDisplay = ({
    error = null,
    errorCode = null,
    errorType = 'general', // 'general', 'api', 'validation', 'network', 'auth', 'quota'
    title = null,
    message = null,
    showRetry = true,
    showDetails = false,
    showSupport = true,
    onRetry = null,
    onDismiss = null,
    retryText = null,
    className = ''
}) => {
    const [showErrorDetails, setShowErrorDetails] = useState(false);

    // Error type configurations
    const errorConfigs = {
        general: {
            icon: '⚠️',
            title: __('Something went wrong', 'rwp-creator-suite'),
            color: '#dc3545',
            showRetry: true
        },
        api: {
            icon: '🔌',
            title: __('API Error', 'rwp-creator-suite'),
            color: '#fd7e14',
            showRetry: true
        },
        validation: {
            icon: '📝',
            title: __('Validation Error', 'rwp-creator-suite'),
            color: '#ffc107',
            showRetry: false
        },
        network: {
            icon: '🌐',
            title: __('Network Error', 'rwp-creator-suite'),
            color: '#dc3545',
            showRetry: true
        },
        auth: {
            icon: '🔐',
            title: __('Authentication Error', 'rwp-creator-suite'),
            color: '#6f42c1',
            showRetry: false
        },
        quota: {
            icon: '📊',
            title: __('Usage Limit Reached', 'rwp-creator-suite'),
            color: '#e83e8c',
            showRetry: false
        }
    };

    const config = errorConfigs[errorType] || errorConfigs.general;

    // Get error message based on type and error object
    const getErrorMessage = () => {
        if (message) return message;

        if (error) {
            // Handle WP_Error objects
            if (error.code && error.message) {
                return error.message;
            }
            
            // Handle standard Error objects
            if (error.message) {
                return error.message;
            }
            
            // Handle string errors
            if (typeof error === 'string') {
                return error;
            }
        }

        // Default messages by type
        const defaultMessages = {
            general: __('An unexpected error occurred. Please try again.', 'rwp-creator-suite'),
            api: __('Unable to connect to the service. Please check your connection and try again.', 'rwp-creator-suite'),
            validation: __('Please check your input and try again.', 'rwp-creator-suite'),
            network: __('Network connection failed. Please check your internet connection.', 'rwp-creator-suite'),
            auth: __('Authentication failed. Please log in and try again.', 'rwp-creator-suite'),
            quota: __('You have reached your usage limit. Please upgrade your plan or try again later.', 'rwp-creator-suite')
        };

        return defaultMessages[errorType] || defaultMessages.general;
    };

    // Get helpful suggestions based on error type
    const getSuggestions = () => {
        const suggestions = {
            general: [
                __('Refresh the page and try again', 'rwp-creator-suite'),
                __('Check if the issue persists', 'rwp-creator-suite'),
                __('Contact support if the problem continues', 'rwp-creator-suite')
            ],
            api: [
                __('Check your internet connection', 'rwp-creator-suite'),
                __('Verify your API keys are configured correctly', 'rwp-creator-suite'),
                __('Try again in a few moments', 'rwp-creator-suite')
            ],
            validation: [
                __('Review the highlighted fields', 'rwp-creator-suite'),
                __('Ensure all required information is provided', 'rwp-creator-suite'),
                __('Check character limits and format requirements', 'rwp-creator-suite')
            ],
            network: [
                __('Check your internet connection', 'rwp-creator-suite'),
                __('Try refreshing the page', 'rwp-creator-suite'),
                __('Disable any VPN or proxy temporarily', 'rwp-creator-suite')
            ],
            auth: [
                __('Log out and log back in', 'rwp-creator-suite'),
                __('Clear your browser cache', 'rwp-creator-suite'),
                __('Check if your session has expired', 'rwp-creator-suite')
            ],
            quota: [
                __('Upgrade your plan for higher limits', 'rwp-creator-suite'),
                __('Wait for your usage to reset', 'rwp-creator-suite'),
                __('Consider using fewer platforms per request', 'rwp-creator-suite')
            ]
        };

        return suggestions[errorType] || suggestions.general;
    };

    // Get error code if available
    const getErrorCode = () => {
        if (errorCode) return errorCode;
        if (error && error.code) return error.code;
        return null;
    };

    const displayTitle = title || config.title;
    const displayMessage = getErrorMessage();
    const suggestions = getSuggestions();
    const displayErrorCode = getErrorCode();
    const canRetry = showRetry && config.showRetry && onRetry;

    return (
        <div className={`rwp-error-display ${className}`}>
            <div className="error-container" style={{ borderColor: config.color }}>
                <div className="error-header">
                    <div className="error-icon">{config.icon}</div>
                    <div className="error-title-section">
                        <h3 className="error-title" style={{ color: config.color }}>
                            {displayTitle}
                        </h3>
                        {displayErrorCode && (
                            <span className="error-code">
                                {sprintf(__('Error Code: %s', 'rwp-creator-suite'), displayErrorCode)}
                            </span>
                        )}
                    </div>
                </div>

                <div className="error-content">
                    <p className="error-message">
                        {displayMessage}
                    </p>

                    {/* Suggestions */}
                    <div className="error-suggestions">
                        <h4>{__('Try these steps:', 'rwp-creator-suite')}</h4>
                        <ul>
                            {suggestions.map((suggestion, index) => (
                                <li key={index}>{suggestion}</li>
                            ))}
                        </ul>
                    </div>

                    {/* Actions */}
                    <div className="error-actions">
                        {canRetry && (
                            <Button
                                isPrimary
                                onClick={onRetry}
                                className="retry-button"
                            >
                                {retryText || __('Try Again', 'rwp-creator-suite')}
                            </Button>
                        )}

                        {onDismiss && (
                            <Button
                                isSecondary
                                onClick={onDismiss}
                                className="dismiss-button"
                            >
                                {__('Dismiss', 'rwp-creator-suite')}
                            </Button>
                        )}

                        {showDetails && error && (
                            <Button
                                isLink
                                onClick={() => setShowErrorDetails(!showErrorDetails)}
                                className="details-toggle"
                            >
                                {showErrorDetails 
                                    ? __('Hide Details', 'rwp-creator-suite')
                                    : __('Show Details', 'rwp-creator-suite')
                                }
                            </Button>
                        )}
                    </div>

                    {/* Error Details */}
                    {showErrorDetails && error && (
                        <div className="error-details">
                            <h5>{__('Technical Details:', 'rwp-creator-suite')}</h5>
                            <pre className="error-stack">
                                {typeof error === 'object' 
                                    ? JSON.stringify(error, null, 2)
                                    : String(error)
                                }
                            </pre>
                        </div>
                    )}

                    {/* Support Contact */}
                    {showSupport && (
                        <div className="error-support">
                            <p className="support-text">
                                {__('Still having trouble?', 'rwp-creator-suite')} {' '}
                                <a 
                                    href="mailto:support@example.com" 
                                    className="support-link"
                                >
                                    {__('Contact Support', 'rwp-creator-suite')}
                                </a>
                            </p>
                        </div>
                    )}
                </div>
            </div>

            <style jsx>{`
                .rwp-error-display {
                    margin: 16px 0;
                }

                .error-container {
                    background: #fff;
                    border: 2px solid;
                    border-radius: 8px;
                    padding: 20px;
                    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                }

                .error-header {
                    display: flex;
                    align-items: flex-start;
                    gap: 12px;
                    margin-bottom: 16px;
                }

                .error-icon {
                    font-size: 32px;
                    line-height: 1;
                    flex-shrink: 0;
                }

                .error-title-section {
                    flex: 1;
                }

                .error-title {
                    margin: 0;
                    font-size: 20px;
                    font-weight: 600;
                    line-height: 1.2;
                }

                .error-code {
                    display: block;
                    font-size: 12px;
                    color: #666;
                    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
                    margin-top: 4px;
                }

                .error-content {
                    color: #1e1e1e;
                }

                .error-message {
                    font-size: 16px;
                    line-height: 1.5;
                    margin: 0 0 20px 0;
                    color: #333;
                }

                .error-suggestions {
                    background: #f8f9fa;
                    border: 1px solid #e9ecef;
                    border-radius: 6px;
                    padding: 16px;
                    margin-bottom: 20px;
                }

                .error-suggestions h4 {
                    margin: 0 0 12px 0;
                    font-size: 16px;
                    color: #1e1e1e;
                }

                .error-suggestions ul {
                    margin: 0;
                    padding-left: 20px;
                }

                .error-suggestions li {
                    margin-bottom: 8px;
                    color: #555;
                }

                .error-actions {
                    display: flex;
                    gap: 12px;
                    flex-wrap: wrap;
                    align-items: center;
                    margin-bottom: 16px;
                }

                .error-details {
                    background: #f1f3f4;
                    border: 1px solid #dadce0;
                    border-radius: 6px;
                    padding: 16px;
                    margin-top: 16px;
                }

                .error-details h5 {
                    margin: 0 0 12px 0;
                    font-size: 14px;
                    color: #1e1e1e;
                }

                .error-stack {
                    background: #fff;
                    border: 1px solid #e0e0e0;
                    border-radius: 4px;
                    padding: 12px;
                    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
                    font-size: 12px;
                    color: #666;
                    overflow-x: auto;
                    white-space: pre-wrap;
                    word-break: break-word;
                    max-height: 200px;
                    overflow-y: auto;
                }

                .error-support {
                    border-top: 1px solid #e0e0e0;
                    padding-top: 16px;
                    margin-top: 16px;
                    text-align: center;
                }

                .support-text {
                    margin: 0;
                    font-size: 14px;
                    color: #666;
                }

                .support-link {
                    color: #007cba;
                    text-decoration: none;
                    font-weight: 500;
                }

                .support-link:hover {
                    text-decoration: underline;
                }

                /* Responsive adjustments */
                @media (max-width: 480px) {
                    .error-container {
                        padding: 16px;
                    }
                    
                    .error-actions {
                        flex-direction: column;
                        align-items: stretch;
                    }
                    
                    .error-actions .components-button {
                        justify-content: center;
                    }
                }
            `}</style>
        </div>
    );
};

// Preset error components for common scenarios
export const ErrorPresets = {
    APIError: (props) => (
        <ErrorDisplay
            errorType="api"
            showRetry={true}
            showDetails={true}
            {...props}
        />
    ),
    
    ValidationError: (props) => (
        <ErrorDisplay
            errorType="validation"
            showRetry={false}
            showSupport={false}
            {...props}
        />
    ),
    
    NetworkError: (props) => (
        <ErrorDisplay
            errorType="network"
            showRetry={true}
            {...props}
        />
    ),
    
    AuthError: (props) => (
        <ErrorDisplay
            errorType="auth"
            showRetry={false}
            {...props}
        />
    ),
    
    QuotaError: (props) => (
        <ErrorDisplay
            errorType="quota"
            showRetry={false}
            showSupport={true}
            {...props}
        />
    )
};

export default ErrorDisplay;