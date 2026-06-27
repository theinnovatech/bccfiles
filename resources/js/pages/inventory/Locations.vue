<template>
    <CrudPage
        title="Location"
        endpoint="/locations"
        :embedded="embedded"
        :columns="[
            { field: 'name', header: 'Name' },
            { field: 'code', header: 'Code' },
            { field: 'description', header: 'Description' },
        ]"
        :fields="[
            { key: 'name', label: 'Name' },
            { key: 'code', label: 'Code' },
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
