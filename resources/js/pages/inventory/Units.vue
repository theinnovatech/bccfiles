<template>
    <CrudPage
        title="Unit"
        endpoint="/units"
        :embedded="embedded"
        :columns="[
            { field: 'name', header: 'Name' },
            { field: 'abbreviation', header: 'Abbreviation' },
        ]"
        :fields="[
            { key: 'name', label: 'Name', required: true },
            { key: 'abbreviation', label: 'Abbreviation', required: true },
        ]"
    />
</template>

<script setup>
import CrudPage from '../../components/CrudPage.vue';

defineProps({
    embedded: { type: Boolean, default: false },
});
</script>
