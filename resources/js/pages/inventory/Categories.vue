<template>
    <CrudPage
        title="Category"
        endpoint="/categories"
        :embedded="embedded"
        :columns="[
            { field: 'name', header: 'Name' },
            { field: 'description', header: 'Description' },
        ]"
        :fields="[
            { key: 'name', label: 'Name' },
            { key: 'description', label: 'Description', type: 'textarea' },
            { key: 'is_active', label: 'Active', type: 'checkbox' },
        ]"
    />
</template>

<script setup>
import CrudPage from '../../components/CrudPage.vue';

defineProps({
    embedded: { type: Boolean, default: false },
});
</script>
