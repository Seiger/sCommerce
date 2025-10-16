import type {Config} from '@docusaurus/types';
import type {ThemeConfig} from '@docusaurus/preset-classic';

const config: Config = {
    title: 'sCommerce Docs',
    tagline: 'Complete e-commerce solution for Evolution CMS. Build powerful online stores with products, orders, payments, and more.',
    url: 'https://seiger.github.io',
    baseUrl: '/sCommerce/',
    favicon: 'img/logo.svg',

    // GitHub Pages
    organizationName: 'Seiger',
    projectName: 'sCommerce',
    deploymentBranch: 'gh-pages',

    onBrokenLinks: 'throw',
    markdown: {
        hooks: {
            onBrokenMarkdownLinks: 'warn',
        },
    },

    i18n: {
        defaultLocale: 'en',
        locales: ['en', 'uk', 'ru'],
        localeConfigs: {
            en: { label: 'English', htmlLang: 'en' },
            uk: { label: 'Українська', htmlLang: 'uk' },
            ru: { label: 'Русский', htmlLang: 'ru' },
        },
    },

    presets: [
        [
            'classic',
            {
                docs: {
                    path: 'pages',
                    routeBasePath: '/',
                    sidebarPath: require.resolve('./sidebars.ts'),
                    editLocalizedFiles: true,
                    includeCurrentVersion: true,
                },
                blog: false,
                theme: {
                    customCss: [
                        require.resolve('./src/css/theme.css'),
                        require.resolve('./src/css/tailwind.css'),
                    ]
                }
            }
        ]
    ],

    themeConfig: {
        navbar: {
            title: 'sCommerce Docs',
            logo: {
                alt: 'sCommerce',
                src: 'img/logo.svg',
                width: 24, height: 24
            },
            items: [
                {type: 'localeDropdown', position: 'right'}
            ]
        }
    } satisfies ThemeConfig
};

export default config;