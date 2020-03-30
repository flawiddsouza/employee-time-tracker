<template>
    <div class="modal2">
        <div class="modal-window">
            <p style="font-size: 1.2em"><template v-if="!message">Are you sure?</template><template v-else>{{ message }}</template></p>
            <div class="ta-l" style="margin-top: -0.5em" v-if="$slots.default">
                <slot></slot>
            </div>
            <div class="actions">
                <button class="btn btn-danger" @click.stop="onCancel">Cancel</button>
                <button class="btn btn-success" @click.stop="onConfirm">{{ confirmLabel || 'Yes' }}</button>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'confirm-modal',
    props: {
        open: Boolean,
        message: String,
        confirmLabel: String
    },
    methods: {
        onConfirm() {
            this.$emit('confirm')
        },
        onCancel() {
            this.$emit('cancel')
        }
    }
}
</script>

<style lang="scss" scoped>
.modal2 {
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    right: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 2;

    .modal-window {
        position: absolute;
        top: 50%;
        left: 50%;
        transition: 0.5s;
        min-width: 350px;
        max-width: 600px;
        background: white;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
        transform: translate(-50%, -50%);
        padding: 1em;
        color: black;
        text-align: center;
        border-radius: 0.3em;
    }

    .modal-window .actions {
        display: flex;
        justify-content: flex-end;

        button {
            margin: 4px;
            padding: 6px 8px;
            cursor: pointer;
            min-width: 59px;
            font-size: 1.1em;
            font-weight: bold;
        }
    }
}
</style>
