import { createMoveRowCommand } from './commands/move-row-command.js';

function executeCommand(context, command) {
    if (typeof context.executeCommand === 'function') {
        return context.executeCommand(command);
    }

    return command.execute(context.commandContext || context);
}

export function createReorderController(config = {}) {
    const dragState = {
        active: false,
        fromIndex: -1,
    };

    function beginDrag(payload = {}) {
        if (payload.handle !== 'drag-handle') {
            return { executed: false, reason: 'drag-handle-required' };
        }

        dragState.active = true;
        dragState.fromIndex = Number(payload.rowIndex);
        return { executed: true, fromIndex: dragState.fromIndex };
    }

    function drop(payload = {}) {
        if (!dragState.active) {
            return { executed: false, reason: 'drag-not-active' };
        }

        const command = createMoveRowCommand({
            fromIndex: dragState.fromIndex,
            toIndex: Number(payload.rowIndex),
        });
        const result = executeCommand(config, command);
        dragState.active = false;
        dragState.fromIndex = -1;
        return result;
    }

    function cancel() {
        dragState.active = false;
        dragState.fromIndex = -1;
        return { executed: true };
    }

    return {
        beginDrag,
        drop,
        cancel,
    };
}
