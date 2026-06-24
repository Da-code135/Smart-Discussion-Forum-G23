/** @type {import('tailwindcss').Config} */
export default {
    content: [
        "./resources/views/**/*.blade.php",
        "./resources/js/**/*.js",
        "./app/Livewire/**/*.php",
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ["Inter", "sans-serif"],
            },
            colors: {
                // Grayscale palette per DESIGN.md
                primary: "#000000",
                "on-primary": "#ffffff",
                "primary-container": "#1c1b1b",
                "on-primary-container": "#858383",

                secondary: "#5e5e5e",
                "on-secondary": "#ffffff",
                "secondary-container": "#e1dfdf",
                "on-secondary-container": "#1b1c1c",

                surface: "#f9f9f9",
                "surface-dim": "#dadada",
                "surface-bright": "#f9f9f9",
                "surface-container-lowest": "#ffffff",
                "surface-container-low": "#f3f3f3",
                "surface-container": "#eeeeee",
                "surface-container-high": "#e8e8e8",
                "surface-container-highest": "#e2e2e2",
                "on-surface": "#1a1c1c",
                "on-surface-variant": "#444748",

                outline: "#747878",
                "outline-variant": "#c4c7c7",

                background: "#f9f9f9",
                "on-background": "#1a1c1c",

                error: "#ba1a1a",
                "on-error": "#ffffff",
                "error-container": "#ffdad6",
                "on-error-container": "#93000a",

                // Aliases for Tailwind
                gray: {
                    50: "#f9f9f9",
                    100: "#f3f3f3",
                    200: "#eeeeee",
                    300: "#e8e8e8",
                    400: "#e2e2e2",
                    500: "#d1d1d1",
                    600: "#c4c7c7",
                    700: "#747878",
                    800: "#5e5e5e",
                    900: "#1a1c1c",
                },
            },
            spacing: {
                xs: "4px",
                sm: "8px",
                md: "16px",
                lg: "24px",
                xl: "32px",
                xxl: "48px",
            },
            borderRadius: {
                sm: "0.25rem", // 4px
                DEFAULT: "0.5rem", // 8px - standard per DESIGN.md
                md: "0.75rem", // 12px
                lg: "1rem", // 16px
                xl: "1.5rem", // 24px for larger containers
            },
            fontSize: {
                // Headlines per DESIGN.md
                "headline-xl": [
                    "48px",
                    {
                        fontWeight: "700",
                        lineHeight: "56px",
                        letterSpacing: "-0.02em",
                    },
                ],
                "headline-lg": [
                    "32px",
                    {
                        fontWeight: "700",
                        lineHeight: "40px",
                        letterSpacing: "-0.02em",
                    },
                ],
                "headline-md": [
                    "24px",
                    { fontWeight: "600", lineHeight: "32px" },
                ],
                "headline-sm": [
                    "20px",
                    { fontWeight: "600", lineHeight: "28px" },
                ],

                // Body text per DESIGN.md
                "body-lg": ["18px", { fontWeight: "400", lineHeight: "28px" }],
                "body-md": ["16px", { fontWeight: "400", lineHeight: "24px" }],
                "body-sm": ["14px", { fontWeight: "400", lineHeight: "20px" }],

                // Labels per DESIGN.md
                "label-md": [
                    "14px",
                    {
                        fontWeight: "600",
                        lineHeight: "16px",
                        letterSpacing: "0.05em",
                    },
                ],
                "label-sm": ["12px", { fontWeight: "500", lineHeight: "16px" }],
            },
            boxShadow: {
                // Ambient shadows only - diffuse, low-opacity per DESIGN.md
                "ambient-sm": "0 1px 3px rgba(0, 0, 0, 0.05)",
                "ambient-md": "0 4px 8px rgba(0, 0, 0, 0.08)",
                // No default shadow - use ambient variants
                none: "none",
            },
            borderWidth: {
                DEFAULT: "1px",
                0: "0",
                2: "2px",
            },
        },
    },
    plugins: [
        require("@tailwindcss/forms"),
        require("@tailwindcss/typography"),
    ],
};
