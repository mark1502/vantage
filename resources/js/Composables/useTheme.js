import { ref } from 'vue'

export const AVAILABLE_THEMES = [
    'light', 'dark', 'corporate', 'retro',
    'aqua', 'aqua2', 'dracula', 'business', 'night', 'coffee', 'caramellatte', 'nord', 'dim', 'winter',
    'cmyk', 'fantasy', 'abyss', 'bumblebee',
]

const theme = ref(localStorage.getItem('theme') || 'light')

export const useTheme = () => {

    const setTheme = (newTheme) => {
        theme.value = newTheme
        document.documentElement.setAttribute('data-theme', newTheme)
        localStorage.setItem('theme', newTheme)
    }

    const initTheme = (savedTheme = null) => {
        const themeToApply = savedTheme ?? localStorage.getItem('theme') ?? 'light'
        const validated = AVAILABLE_THEMES.includes(themeToApply) ? themeToApply : 'light'
        setTheme(validated)
    }

    return {
        theme,
        themes: AVAILABLE_THEMES,
        setTheme,
        initTheme,
    }
}
