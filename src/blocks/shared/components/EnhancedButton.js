/**
 * Enhanced Button Component
 *
 * Modern button component with gradient backgrounds, hover effects,
 * and loading states using Tailwind/DaisyUI classes.
 */

import { __ } from '@wordpress/i18n';

const EnhancedButton = ( {
	variant = 'primary',
	size = 'medium',
	children,
	disabled = false,
	loading = false,
	onClick,
	className = '',
	icon = null,
	...props
} ) => {
	const getVariantClasses = () => {
		switch ( variant ) {
			case 'primary':
				return `
					bg-gradient-to-r from-blue-600 to-blue-700
					hover:from-blue-700 hover:to-blue-800
					text-white border-0 
					shadow-lg shadow-blue-500/25
					hover:shadow-xl hover:shadow-blue-500/35
					hover:-translate-y-0.5
					active:translate-y-0 active:shadow-lg active:shadow-blue-500/25
					disabled:opacity-60 disabled:cursor-not-allowed 
					disabled:hover:translate-y-0 disabled:hover:shadow-lg disabled:hover:shadow-blue-500/25
				`;
			case 'secondary':
				return `
					bg-white text-blue-600 border-2 border-blue-600
					hover:bg-blue-600 hover:text-white
					hover:-translate-y-0.5 hover:shadow-lg hover:shadow-blue-500/25
					active:translate-y-0 active:shadow-md
					disabled:opacity-60 disabled:cursor-not-allowed
					disabled:hover:translate-y-0 disabled:hover:bg-white disabled:hover:text-blue-600
				`;
			case 'danger':
				return `
					bg-gradient-to-r from-red-600 to-red-700
					hover:from-red-700 hover:to-red-800
					text-white border-0
					shadow-lg shadow-red-500/25
					hover:shadow-xl hover:shadow-red-500/35
					hover:-translate-y-0.5
					active:translate-y-0 active:shadow-lg active:shadow-red-500/25
					disabled:opacity-60 disabled:cursor-not-allowed
					disabled:hover:translate-y-0 disabled:hover:shadow-lg disabled:hover:shadow-red-500/25
				`;
			case 'ghost':
				return `
					bg-transparent text-gray-600 border border-gray-300
					hover:bg-gray-50 hover:border-gray-400
					active:bg-gray-100
					disabled:opacity-60 disabled:cursor-not-allowed
				`;
			default:
				return getVariantClasses( 'primary' );
		}
	};

	const getSizeClasses = () => {
		switch ( size ) {
			case 'small':
				return 'py-2 px-3 sm:px-4 text-xs sm:text-sm min-h-[32px] sm:min-h-[36px]';
			case 'medium':
				return 'py-2 sm:py-3 px-4 sm:px-6 text-sm sm:text-base min-h-[40px] sm:min-h-[44px]';
			case 'large':
				return 'py-3 sm:py-4 px-6 sm:px-8 text-base sm:text-lg min-h-[48px] sm:min-h-[52px]';
			default:
				return getSizeClasses( 'medium' );
		}
	};

	const baseClasses = `
		rounded-xl font-semibold cursor-pointer
		transition-all duration-200 ease-in-out
		inline-flex items-center justify-center gap-2
		relative overflow-hidden
		focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2
		${getSizeClasses()}
		${getVariantClasses()}
		${className}
	`;

	return (
		<button
			className={ baseClasses }
			disabled={ disabled || loading }
			onClick={ onClick }
			{ ...props }
		>
			{ loading && (
				<div className="absolute inset-0 flex items-center justify-center bg-inherit">
					<div className="w-5 h-5 border-2 border-current border-t-transparent rounded-full animate-spin" />
				</div>
			) }
			
			<span className={ `flex items-center gap-2 ${loading ? 'opacity-0' : 'opacity-100'}` }>
				{ icon && <span className="flex-shrink-0">{ icon }</span> }
				{ children }
			</span>
		</button>
	);
};

export default EnhancedButton;