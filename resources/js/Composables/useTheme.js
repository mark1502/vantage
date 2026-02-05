import { ref } from 'vue'
import { usePage } from '@inertiajs/vue3'

export const useTheme = () => {
    // Create a reactive theme variable
    const theme = ref(localStorage.getItem('theme') || 'light')

    // Function to update theme
    const setTheme = (newTheme) => {
        theme.value = newTheme
        localStorage.setItem('theme', newTheme)
        
        // Remove existing theme classes
        document.documentElement.classList.remove('light', 'dark')
        // Add new theme class
        document.documentElement.classList.add(newTheme)
        
        // If you still need the data-theme attribute for DaisyUI, set it on body instead
        document.body.setAttribute('data-theme', newTheme)
    }

    // Initialize theme on mount
    const initTheme = () => {
        const savedTheme = localStorage.getItem('theme')
        if (savedTheme) {
            setTheme(savedTheme)
        } else {
            // Check system preference
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches
            setTheme(prefersDark ? 'dark' : 'light')
        }
    }

    return {
        theme,
        setTheme,
        initTheme
    }
}
