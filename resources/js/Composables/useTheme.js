import { ref } from 'vue'

export const AVAILABLE_THEMES = [
    'light', 'corporate', 'autumn', 'fantasy', 'bumblebee', 'winter', 'nord', 'caramellatte', 'retro', 'coffee', 
    'aqua', 'night', 'dark', 'dracula', 'business', 'dim', 
    
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

    const previewTheme = (previewThemeName) => {
        document.documentElement.setAttribute('data-theme', previewThemeName)
    }

    const revertPreview = () => {
        document.documentElement.setAttribute('data-theme', theme.value)
    }

    return {
        theme,
        themes: AVAILABLE_THEMES,
        setTheme,
        initTheme,
        previewTheme,
        revertPreview,
    }
}
