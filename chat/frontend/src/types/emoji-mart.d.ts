// Declarações de tipo para @emoji-mart (pacotes sem bundled .d.ts)
declare module '@emoji-mart/data' {
    const data: Record<string, unknown>
    export default data
}

declare module '@emoji-mart/react' {
    import { ComponentType } from 'react'

    interface PickerProps {
        data: Record<string, unknown>
        locale?: string
        theme?: 'light' | 'dark' | 'auto'
        onEmojiSelect?: (emoji: { native: string; id: string; unified: string }) => void
        [key: string]: unknown
    }

    const Picker: ComponentType<PickerProps>
    export default Picker
}
