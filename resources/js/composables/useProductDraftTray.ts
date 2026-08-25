import { ref } from 'vue';

export interface AiProductAction {
    tool: string;
    args: Record<string, unknown>;
    result: Record<string, any>;
}

export interface ProductDraftSource {
    action: AiProductAction;
    actionIndex: number;
    messageId: string;
}

const visible = ref(false);
const sources = ref<ProductDraftSource[]>([]);

export function useProductDraftTray() {
    const open = (messageId: string, actions: AiProductAction[]) => {
        sources.value = actions
            .map((action, actionIndex) => ({ action, actionIndex, messageId }))
            .filter(({ action }) => action.tool === 'add_product' && action.result?.is_preview !== false && !action.result?.is_discarded);

        visible.value = sources.value.length > 0;
    };

    const close = () => {
        visible.value = false;
    };

    return {
        visible,
        sources,
        open,
        close,
    };
}
