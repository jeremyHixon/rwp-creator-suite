// Tailwind CSS v3 configuration for WordPress plugin blocks
module.exports = {
	// Content sources for scanning classes
	content: [
		'./src/blocks/**/*.{js,jsx,ts,tsx}',
		'./src/blocks/**/*.php',
		'./src/blocks/**/*.scss',
	],

	// Prefix all utilities to prevent conflicts with theme styles
	prefix: 'blk-',

	// Disable preflight and container to prevent theme interference
	corePlugins: {
		preflight: false,
		container: false,
	},

	// Plugin configuration
	plugins: [
		require( 'daisyui' ),
		require( '@tailwindcss/container-queries' ),
	],

	// DaisyUI specific configuration
	daisyui: {
		prefix: 'dui-',
		base: false,
		themes: [ 'light' ],
		utils: false,
		logs: false,
	},
};
