/**
 * Modern Tabs Component
 *
 * Modern tab navigation with pill-style active states, badges for counts,
 * and responsive behavior using Tailwind/DaisyUI classes.
 */

import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';

const ModernTabs = ( { 
	tabs, 
	activeTab, 
	onTabChange, 
	counts = {},
	className = '',
	size = 'medium'
} ) => {
	const getSizeClasses = () => {
		switch ( size ) {
			case 'small':
				return {
					container: 'p-1',
					tab: 'py-2 px-3 text-sm min-h-[36px]',
					badge: 'text-xs min-w-[16px] h-4 -top-1 -right-1'
				};
			case 'medium':
				return {
					container: 'p-1',
					tab: 'py-3 px-5 text-base min-h-[44px]',
					badge: 'text-xs min-w-[18px] h-5 -top-2 -right-2'
				};
			case 'large':
				return {
					container: 'p-1.5',
					tab: 'py-4 px-6 text-lg min-h-[52px]',
					badge: 'text-sm min-w-[20px] h-6 -top-2 -right-2'
				};
			default:
				return getSizeClasses( 'medium' );
		}
	};

	const sizeClasses = getSizeClasses();

	const handleTabClick = ( tabId ) => {
		onTabChange( tabId );
	};

	const handleKeyDown = ( e, tabId, tabIndex ) => {
		let nextIndex = tabIndex;

		switch ( e.key ) {
			case 'ArrowRight':
			case 'ArrowDown':
				e.preventDefault();
				nextIndex = ( tabIndex + 1 ) % tabs.length;
				break;
			case 'ArrowLeft':
			case 'ArrowUp':
				e.preventDefault();
				nextIndex = ( tabIndex - 1 + tabs.length ) % tabs.length;
				break;
			case 'Home':
				e.preventDefault();
				nextIndex = 0;
				break;
			case 'End':
				e.preventDefault();
				nextIndex = tabs.length - 1;
				break;
			case 'Enter':
			case ' ':
				e.preventDefault();
				handleTabClick( tabId );
				return;
			default:
				return;
		}

		// Focus the next tab
		const nextTab = document.querySelector(`[data-tab-id="${tabs[nextIndex].id}"]`);
		if ( nextTab ) {
			nextTab.focus();
		}
	};

	return (
		<div 
			className={ `
				flex bg-gray-100 rounded-xl overflow-hidden mb-6 
				overflow-x-auto scrollbar-hide
				${sizeClasses.container} ${className}
			` }
			role="tablist"
			aria-label={ __( 'Content sections', 'rwp-creator-suite' ) }
		>
			{ tabs.map( ( tab, index ) => {
				const isActive = activeTab === tab.id;
				const count = counts[ tab.id ] || 0;
				const hasCount = count > 0;
				
				return (
					<button
						key={ tab.id }
						data-tab-id={ tab.id }
						className={ `
							flex-1 min-w-max font-medium cursor-pointer
							transition-all duration-200 ease-in-out
							rounded-lg relative
							flex items-center justify-center
							focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-gray-100
							${sizeClasses.tab}
							${isActive 
								? 'bg-white text-gray-900 shadow-md font-semibold' 
								: 'text-gray-600 hover:text-gray-800 hover:bg-white/50'
							}
						` }
						onClick={ () => handleTabClick( tab.id ) }
						onKeyDown={ ( e ) => handleKeyDown( e, tab.id, index ) }
						role="tab"
						aria-selected={ isActive }
						aria-controls={ `panel-${tab.id}` }
						tabIndex={ isActive ? 0 : -1 }
						id={ `tab-${tab.id}` }
					>
						<span className="flex items-center gap-2">
							{ tab.icon && (
								<span className="flex-shrink-0">{ tab.icon }</span>
							) }
							{ tab.label }
						</span>
						
						{ hasCount && (
							<span 
								className={ `
									absolute bg-blue-500 text-white rounded-full 
									flex items-center justify-center font-semibold
									${sizeClasses.badge}
									${isActive ? 'bg-green-500' : 'bg-blue-500'}
								` }
								aria-label={ `${count} items` }
							>
								{ count > 99 ? '99+' : count }
							</span>
						) }
					</button>
				);
			} ) }
		</div>
	);
};

export default ModernTabs;