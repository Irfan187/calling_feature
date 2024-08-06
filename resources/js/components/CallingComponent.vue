<template>
    <div class="container mt-5">
        <h1>Make a Call</h1>
        <div class="mb-3">
            <label for="to" class="form-label">To</label>
            <input type="text" class="form-control" id="to" name="to" value="+17274257260" required>
        </div>
        <div class="mb-3">
            <label for="from" class="form-label">From</label>
            <input type="text" class="form-control" id="from" name="from" value="+16265401233" required>
        </div>

        <button type="button" class="btn btn-primary" @click="makeCall">Make Call</button>
    </div>
</template>

<script>
import axios from 'axios';
export default {
    name: 'ExampleComponent',
    methods: {
        makeCall() {
            const data = {
                to: document.getElementById('to').value,
                from: document.getElementById('from').value,
            };
            axios.post('/api/make-call', data)
                .then(async (response) => {
                })
                .catch(async (error) => {
                });
        }
    }
};

</script>

<style scoped>
.example {
    text-align: center;
    margin-top: 50px;
}
</style>