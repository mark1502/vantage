import { ref } from 'vue'

const AVAILABLE_THEMES = [
    'light', 'dark', 'corporate', 'retro',
    'aqua', 'aqua2', 'dracula', 'business', 'night', 'coffee', 'caramellatte', 'nord', 'dim', 'winter',
    'cmyk', 'fantasy', 'abyss', 'bumblebee',
]

export const useTheme = () => {
    const theme = ref(localStorage.getItem('theme') || 'light')

    const setTheme = (newTheme) => {
        theme.value = newTheme
        localStorage.setItem('theme', newTheme)
        document.documentElement.setAttribute('data-theme', newTheme)
    }

    const initTheme = () => {
        const savedTheme = localStorage.getItem('theme')
        if (savedTheme && AVAILABLE_THEMES.includes(savedTheme)) {
            setTheme(savedTheme)
        } else {
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches
            setTheme(prefersDark ? 'dark' : 'light')
        }
    }

    return {
        theme,
        themes: AVAILABLE_THEMES,
        setTheme,
        initTheme,
    }
}
