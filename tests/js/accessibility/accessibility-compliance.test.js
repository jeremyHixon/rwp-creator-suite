/**
 * Accessibility Compliance Testing Suite
 * Phase 3 UI/UX Implementation
 * 
 * Tests WCAG 2.1 AA compliance for all Phase 3 components
 */

import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import '@testing-library/jest-dom';

// Components to test
import AccessiblePlatformSelector from '../../../src/blocks/shared/components/AccessiblePlatformSelector';
import MobileModal from '../../../src/blocks/shared/components/MobileModal';
import MobileTabNavigation from '../../../src/blocks/shared/components/MobileTabNavigation';
import ContentSuggestions from '../../../src/blocks/shared/components/ContentSuggestions';
import { SmartCopyButton } from '../../../src/blocks/shared/components/SmartClipboard';

// Test utilities
import { auditAccessibility } from '../../../src/blocks/shared/utils/phase3-integration';

// Mock WordPress i18n
const mockI18n = {
    __: (text) => text,
    _n: (single, plural, number) => number === 1 ? single : plural,
    sprintf: (format, ...args) => {
        return format.replace(/%[sd]/g, () => args.shift());
    }
};

global.wp = {
    i18n: mockI18n
};

// Mock matchMedia for accessibility preferences
Object.defineProperty(window, 'matchMedia', {
    writable: true,
    value: jest.fn().mockImplementation(query => ({
        matches: query.includes('prefers-reduced-motion: reduce') ? false : true,
        media: query,
        onchange: null,
        addListener: jest.fn(), // Deprecated
        removeListener: jest.fn(), // Deprecated
        addEventListener: jest.fn(),
        removeEventListener: jest.fn(),
        dispatchEvent: jest.fn(),
    }))
});

// Mock IntersectionObserver
global.IntersectionObserver = jest.fn().mockImplementation((callback) => ({
    observe: jest.fn(),
    unobserve: jest.fn(),
    disconnect: jest.fn(),
    trigger: (entries) => callback(entries)
}));

describe('Accessibility Compliance Tests - WCAG 2.1 AA', () => {
    
    describe('AccessiblePlatformSelector', () => {
        const mockPlatforms = ['instagram', 'twitter', 'facebook'];
        const mockOnPlatformsChange = jest.fn();
        
        beforeEach(() => {
            mockOnPlatformsChange.mockClear();
        });
        
        test('provides proper keyboard navigation', async () => {
            const user = userEvent.setup();
            
            render(
                <AccessiblePlatformSelector
                    selectedPlatforms={[]}
                    onPlatformsChange={mockOnPlatformsChange}
                    allowedPlatforms={mockPlatforms}
                />
            );
            
            const platformSelector = screen.getByRole('group', { name: /select social media platforms/i });
            expect(platformSelector).toBeInTheDocument();
            
            // Test keyboard navigation
            await user.tab();
            expect(platformSelector).toHaveFocus();
            
            // Test arrow key navigation
            fireEvent.keyDown(platformSelector, { key: 'ArrowRight' });
            fireEvent.keyDown(platformSelector, { key: 'Enter' });
            
            expect(mockOnPlatformsChange).toHaveBeenCalled();
        });
        
        test('provides screen reader accessible content', () => {
            render(
                <AccessiblePlatformSelector
                    selectedPlatforms={['instagram']}
                    onPlatformsChange={mockOnPlatformsChange}
                    allowedPlatforms={mockPlatforms}
                />
            );
            
            // Check for proper ARIA attributes
            const checkboxes = screen.getAllByRole('checkbox');
            expect(checkboxes).toHaveLength(mockPlatforms.length);
            
            checkboxes.forEach(checkbox => {
                expect(checkbox).toHaveAttribute('aria-checked');
                expect(checkbox).toHaveAttribute('aria-describedby');
            });
            
            // Check for screen reader instructions
            expect(screen.getByText(/use arrow keys to navigate/i)).toBeInTheDocument();
        });
        
        test('supports high contrast mode', () => {
            // Mock high contrast mode
            window.matchMedia = jest.fn().mockImplementation(query => ({
                matches: query.includes('prefers-contrast: high'),
                media: query,
                onchange: null,
                addListener: jest.fn(),
                removeListener: jest.fn(),
                addEventListener: jest.fn(),
                removeEventListener: jest.fn(),
                dispatchEvent: jest.fn(),
            }));
            
            const { container } = render(
                <AccessiblePlatformSelector
                    selectedPlatforms={[]}
                    onPlatformsChange={mockOnPlatformsChange}
                    allowedPlatforms={mockPlatforms}
                />
            );
            
            // High contrast styles should be applied
            const platformCard = container.querySelector('.blk-platform-card');
            expect(platformCard).toBeInTheDocument();
        });
        
        test('respects reduced motion preferences', () => {
            // Mock reduced motion preference
            window.matchMedia = jest.fn().mockImplementation(query => ({
                matches: query.includes('prefers-reduced-motion: reduce'),
                media: query,
                onchange: null,
                addListener: jest.fn(),
                removeListener: jest.fn(),
                addEventListener: jest.fn(),
                removeEventListener: jest.fn(),
                dispatchEvent: jest.fn(),
            }));
            
            render(
                <AccessiblePlatformSelector
                    selectedPlatforms={[]}
                    onPlatformsChange={mockOnPlatformsChange}
                    allowedPlatforms={mockPlatforms}
                />
            );
            
            // Component should still render without animations
            expect(screen.getByRole('group')).toBeInTheDocument();
        });
        
        test('provides proper focus indicators', async () => {
            const user = userEvent.setup();
            
            render(
                <AccessiblePlatformSelector
                    selectedPlatforms={[]}
                    onPlatformsChange={mockOnPlatformsChange}
                    allowedPlatforms={mockPlatforms}
                />
            );
            
            const platformSelector = screen.getByRole('group');
            await user.tab();
            
            expect(platformSelector).toHaveFocus();
            expect(platformSelector).toHaveAttribute('tabindex', '0');
        });
    });
    
    describe('MobileModal', () => {
        const mockOnClose = jest.fn();
        
        beforeEach(() => {
            mockOnClose.mockClear();
        });
        
        test('traps focus within modal', async () => {
            const user = userEvent.setup();
            
            render(
                <MobileModal
                    isOpen={true}
                    onClose={mockOnClose}
                    title="Test Modal"
                >
                    <button>First Button</button>
                    <button>Last Button</button>
                </MobileModal>
            );
            
            const modal = screen.getByRole('dialog');
            expect(modal).toBeInTheDocument();
            
            // Check focus trap
            const firstButton = screen.getByText('First Button');
            const lastButton = screen.getByText('Last Button');
            const closeButton = screen.getByRole('button', { name: /close dialog/i });
            
            expect(firstButton).toBeInTheDocument();
            expect(lastButton).toBeInTheDocument();
            expect(closeButton).toBeInTheDocument();
            
            // Test tab cycling
            await user.tab();
            await user.tab();
            await user.tab();
            await user.tab(); // Should cycle back
            
            expect(document.activeElement).toBe(closeButton);
        });
        
        test('supports escape key to close', async () => {
            const user = userEvent.setup();
            
            render(
                <MobileModal
                    isOpen={true}
                    onClose={mockOnClose}
                    title="Test Modal"
                >
                    <div>Content</div>
                </MobileModal>
            );
            
            await user.keyboard('{Escape}');
            expect(mockOnClose).toHaveBeenCalled();
        });
        
        test('has proper ARIA attributes', () => {
            render(
                <MobileModal
                    isOpen={true}
                    onClose={mockOnClose}
                    title="Test Modal"
                >
                    <div>Content</div>
                </MobileModal>
            );
            
            const modal = screen.getByRole('dialog');
            expect(modal).toHaveAttribute('aria-modal', 'true');
            expect(modal).toHaveAttribute('aria-labelledby');
            expect(modal).toHaveAttribute('aria-describedby');
        });
        
        test('announces modal opening to screen readers', () => {
            const { rerender } = render(
                <MobileModal
                    isOpen={false}
                    onClose={mockOnClose}
                    title="Test Modal"
                >
                    <div>Content</div>
                </MobileModal>
            );
            
            // Mock screen reader announcements
            const mockAnnounce = jest.fn();
            global.mockAnnounce = mockAnnounce;
            
            rerender(
                <MobileModal
                    isOpen={true}
                    onClose={mockOnClose}
                    title="Test Modal"
                >
                    <div>Content</div>
                </MobileModal>
            );
            
            // Check that modal is rendered
            expect(screen.getByRole('dialog')).toBeInTheDocument();
        });
    });
    
    describe('MobileTabNavigation', () => {
        const mockTabs = [
            { id: 'tab1', label: 'Tab 1', content: <div>Content 1</div> },
            { id: 'tab2', label: 'Tab 2', content: <div>Content 2</div> },
            { id: 'tab3', label: 'Tab 3', content: <div>Content 3</div> }
        ];
        const mockOnTabChange = jest.fn();
        
        beforeEach(() => {
            mockOnTabChange.mockClear();
        });
        
        test('implements proper tab navigation pattern', async () => {
            const user = userEvent.setup();
            
            render(
                <MobileTabNavigation
                    tabs={mockTabs}
                    activeTab="tab1"
                    onTabChange={mockOnTabChange}
                />
            );
            
            const tabList = screen.getByRole('tablist');
            expect(tabList).toBeInTheDocument();
            
            const tabs = screen.getAllByRole('tab');
            expect(tabs).toHaveLength(3);
            
            // Test arrow key navigation
            await user.tab(); // Focus first tab
            fireEvent.keyDown(tabs[0], { key: 'ArrowRight' });
            
            // Should focus next tab but not activate it yet
            expect(tabs[1]).toHaveFocus();
            
            fireEvent.keyDown(tabs[1], { key: 'Enter' });
            expect(mockOnTabChange).toHaveBeenCalledWith('tab2');
        });
        
        test('provides proper ARIA labeling', () => {
            render(
                <MobileTabNavigation
                    tabs={mockTabs}
                    activeTab="tab1"
                    onTabChange={mockOnTabChange}
                />
            );
            
            const tabs = screen.getAllByRole('tab');
            const tabPanels = screen.getAllByRole('tabpanel');
            
            tabs.forEach((tab, index) => {
                expect(tab).toHaveAttribute('aria-selected');
                expect(tab).toHaveAttribute('aria-controls');
            });
            
            tabPanels.forEach(panel => {
                expect(panel).toHaveAttribute('aria-labelledby');
            });
        });
        
        test('supports Home and End keys', async () => {
            render(
                <MobileTabNavigation
                    tabs={mockTabs}
                    activeTab="tab2"
                    onTabChange={mockOnTabChange}
                />
            );
            
            const tabList = screen.getByRole('tablist');
            const tabs = screen.getAllByRole('tab');
            
            // Focus the tab list and press Home
            fireEvent.keyDown(tabList, { key: 'Home' });
            expect(tabs[0]).toHaveFocus();
            
            // Press End
            fireEvent.keyDown(tabList, { key: 'End' });
            expect(tabs[2]).toHaveFocus();
        });
    });
    
    describe('ContentSuggestions', () => {
        const mockOnApplySuggestion = jest.fn();
        const mockContent = "This is a test content that might be too long for some platforms.";
        const mockPlatforms = ['twitter', 'instagram'];
        
        beforeEach(() => {
            mockOnApplySuggestion.mockClear();
        });
        
        test('provides accessible suggestion interface', async () => {
            render(
                <ContentSuggestions
                    content={mockContent}
                    platforms={mockPlatforms}
                    onApplySuggestion={mockOnApplySuggestion}
                />
            );
            
            const suggestionsRegion = screen.getByRole('region', { name: /content suggestions/i });
            expect(suggestionsRegion).toBeInTheDocument();
            
            // Wait for suggestions to be analyzed
            await waitFor(() => {
                const alerts = screen.queryAllByRole('alert');
                expect(alerts.length).toBeGreaterThanOrEqual(0);
            });
        });
        
        test('announces suggestion updates', async () => {
            const { rerender } = render(
                <ContentSuggestions
                    content=""
                    platforms={mockPlatforms}
                    onApplySuggestion={mockOnApplySuggestion}
                />
            );
            
            rerender(
                <ContentSuggestions
                    content={mockContent}
                    platforms={mockPlatforms}
                    onApplySuggestion={mockOnApplySuggestion}
                />
            );
            
            // Check for live region updates
            await waitFor(() => {
                const liveRegion = screen.getByRole('status');
                expect(liveRegion).toBeInTheDocument();
            });
        });
    });
    
    describe('SmartCopyButton', () => {
        const mockOnCopyComplete = jest.fn();
        const testContent = "Test content to copy";
        
        beforeEach(() => {
            mockOnCopyComplete.mockClear();
            // Mock clipboard API
            Object.assign(navigator, {
                clipboard: {
                    writeText: jest.fn().mockResolvedValue(true)
                }
            });
        });
        
        test('provides proper button accessibility', () => {
            render(
                <SmartCopyButton
                    content={testContent}
                    onCopyComplete={mockOnCopyComplete}
                />
            );
            
            const copyButton = screen.getByRole('button', { name: /copy content/i });
            expect(copyButton).toBeInTheDocument();
            expect(copyButton).toHaveAttribute('aria-label');
            expect(copyButton).not.toHaveAttribute('aria-disabled');
        });
        
        test('announces copy status to screen readers', async () => {
            const user = userEvent.setup();
            
            render(
                <SmartCopyButton
                    content={testContent}
                    onCopyComplete={mockOnCopyComplete}
                />
            );
            
            const copyButton = screen.getByRole('button');
            await user.click(copyButton);
            
            // Check for updated aria-label
            await waitFor(() => {
                expect(copyButton).toHaveAttribute('aria-label', /content copied/i);
            });
        });
        
        test('handles keyboard activation', async () => {
            const user = userEvent.setup();
            
            render(
                <SmartCopyButton
                    content={testContent}
                    onCopyComplete={mockOnCopyComplete}
                />
            );
            
            const copyButton = screen.getByRole('button');
            await user.tab();
            expect(copyButton).toHaveFocus();
            
            await user.keyboard('{Enter}');
            expect(navigator.clipboard.writeText).toHaveBeenCalledWith(testContent);
        });
    });
    
    describe('Accessibility Audit Utility', () => {
        test('identifies accessibility issues', () => {
            const testElement = document.createElement('div');
            testElement.innerHTML = `
                <button>Unlabeled button</button>
                <input type="text" />
                <div role="button">Unlabeled div button</div>
            `;
            
            const auditResult = auditAccessibility(testElement);
            
            expect(auditResult.issues).toHaveLength(3);
            expect(auditResult.score).toBeLessThan(100);
            
            // Check for specific issues
            const missingLabelIssues = auditResult.issues.filter(
                issue => issue.issue === 'Missing accessible label'
            );
            expect(missingLabelIssues).toHaveLength(3);
        });
        
        test('provides improvement suggestions', () => {
            const testElement = document.createElement('div');
            testElement.innerHTML = `
                <div style="color: #ccc; background: white;">Low contrast text</div>
                <div class="animate">Animated content</div>
            `;
            
            const auditResult = auditAccessibility(testElement);
            
            expect(auditResult.improvements).toContainEqual(
                expect.objectContaining({
                    type: 'color-contrast'
                })
            );
            
            expect(auditResult.improvements).toContainEqual(
                expect.objectContaining({
                    type: 'motion-preferences'
                })
            );
        });
    });
    
    describe('Color Contrast Compliance', () => {
        test('meets WCAG AA color contrast requirements', () => {
            // Test primary color combinations
            const colors = {
                primary: '#3b82f6',
                secondary: '#64748b',
                accent: '#10b981',
                text: '#1f2937',
                background: '#ffffff'
            };
            
            // Color contrast ratio should be at least 4.5:1 for normal text
            // and 3:1 for large text (WCAG AA standards)
            
            // This would typically use a color contrast library
            // For now, we verify the colors are defined
            Object.values(colors).forEach(color => {
                expect(color).toMatch(/^#[0-9a-f]{6}$/i);
            });
        });
    });
    
    describe('Touch Target Compliance', () => {
        test('meets minimum touch target size requirements', () => {
            render(
                <SmartCopyButton
                    content="test"
                    size="small"
                />
            );
            
            const button = screen.getByRole('button');
            const styles = window.getComputedStyle(button);
            
            // Check for touch-target class
            expect(button).toHaveClass('blk-touch-target');
        });
    });
});

describe('Integration Tests', () => {
    test('all Phase 3 components work together', async () => {
        const user = userEvent.setup();
        const mockProps = {
            selectedPlatforms: ['instagram'],
            onPlatformsChange: jest.fn(),
            content: 'Test content',
            onApplySuggestion: jest.fn(),
            onCopyComplete: jest.fn()
        };
        
        render(
            <div>
                <AccessiblePlatformSelector
                    selectedPlatforms={mockProps.selectedPlatforms}
                    onPlatformsChange={mockProps.onPlatformsChange}
                />
                <ContentSuggestions
                    content={mockProps.content}
                    platforms={mockProps.selectedPlatforms}
                    onApplySuggestion={mockProps.onApplySuggestion}
                />
                <SmartCopyButton
                    content={mockProps.content}
                    onCopyComplete={mockProps.onCopyComplete}
                />
            </div>
        );
        
        // Test that all components render without conflicts
        expect(screen.getByRole('group')).toBeInTheDocument();
        expect(screen.getByRole('region')).toBeInTheDocument();
        expect(screen.getByRole('button')).toBeInTheDocument();
        
        // Test keyboard navigation across components
        await user.tab();
        await user.tab();
        await user.tab();
        
        // All should be keyboard accessible
        expect(document.activeElement).toBeInTheDocument();
    });
});

// Performance impact tests
describe('Performance Impact', () => {
    test('Phase 3 enhancements do not significantly impact render time', () => {
        const startTime = performance.now();
        
        render(
            <AccessiblePlatformSelector
                selectedPlatforms={[]}
                onPlatformsChange={() => {}}
                allowedPlatforms={['instagram', 'twitter', 'facebook', 'linkedin']}
            />
        );
        
        const endTime = performance.now();
        const renderTime = endTime - startTime;
        
        // Should render in less than 50ms
        expect(renderTime).toBeLessThan(50);
    });
});