<template>
    <div class="login-field">
        <label class="login-field-label" :for="id">
            {{ label }}
            <span v-if="required" class="text-[#ce1126]">*</span>
        </label>
        <div class="login-field-box" :class="{ 'login-field-box--password': password }">
            <input
                :id="id"
                :value="modelValue"
                :type="password && !visible ? 'password' : type"
                :name="name || id"
                class="login-field-input"
                :class="inputClass"
                :placeholder="placeholder"
                :autocomplete="autocomplete"
                :inputmode="inputmode"
                :maxlength="maxlength"
                :required="required"
                :aria-invalid="error ? 'true' : 'false'"
                :aria-describedby="hint || error ? `${id}-hint` : undefined"
                @input="$emit('update:modelValue', $event.target.value)"
            />
            <i :class="`pi ${icon} login-field-icon`" aria-hidden="true"></i>
            <button
                v-if="password"
                type="button"
                class="login-field-toggle"
                :aria-label="visible ? 'Hide password' : 'Show password'"
                @click="visible = !visible"
            >
                <i :class="visible ? 'pi pi-eye-slash' : 'pi pi-eye'" aria-hidden="true"></i>
            </button>
        </div>
        <p
            v-if="error || hint"
            :id="`${id}-hint`"
            class="mt-1.5 text-xs"
            :class="error ? 'text-red-300' : 'text-[#c8d6f0]'"
        >
            {{ error || hint }}
        </p>
    </div>
</template>

<script setup>
import { ref } from "vue";

defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    modelValue: { type: String, default: "" },
    type: { type: String, default: "text" },
    name: { type: String, default: "" },
    icon: { type: String, default: "pi-pencil" },
    placeholder: { type: String, default: "" },
    autocomplete: { type: String, default: "off" },
    inputmode: { type: String, default: undefined },
    maxlength: { type: [Number, String], default: undefined },
    required: { type: Boolean, default: false },
    password: { type: Boolean, default: false },
    hint: { type: String, default: "" },
    error: { type: String, default: "" },
    inputClass: { type: String, default: "" },
});

defineEmits(["update:modelValue"]);

const visible = ref(false);
</script>
