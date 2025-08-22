/**
 * Shared Block Components Index
 *
 * Exports all shared components for easy importing across blocks.
 */

// Main components
export { default as PlatformSelector } from './components/PlatformSelector';
export { default as ToneSelector } from './components/ToneSelector';
export { default as CharacterCounter } from './components/CharacterCounter';
export { default as CharacterMeter } from './components/CharacterMeter';
export { default as EnhancedButton } from './components/EnhancedButton';
export { default as ModernTabs } from './components/ModernTabs';
export {
	default as LoadingStates,
	LoadingPresets,
} from './components/LoadingStates';
export {
	default as ErrorDisplay,
	ErrorPresets,
} from './components/ErrorDisplay';

// Component utilities and hooks
export * from './utils/platform-utils';
export * from './utils/content-utils';
export * from './hooks/useContentGeneration';
export * from './hooks/useErrorHandler';

// Shared constants
export const PLATFORM_LIMITS = {
	twitter: 280,
	instagram: 2200,
	facebook: 63206,
	linkedin: 3000,
	tiktok: 2200,
	youtube: 5000,
	pinterest: 500,
};

export const SUPPORTED_PLATFORMS = [
	'instagram',
	'twitter',
	'facebook',
	'linkedin',
	'tiktok',
	'youtube',
	'pinterest',
];

export const TONE_OPTIONS = [
	'professional',
	'casual',
	'enthusiastic',
	'authoritative',
	'friendly',
	'inspirational',
	'humorous',
	'educational',
];

export const STYLE_OPTIONS = [
	'informative',
	'storytelling',
	'listicle',
	'howto',
	'opinion',
	'promotional',
	'news',
	'behind_scenes',
];
