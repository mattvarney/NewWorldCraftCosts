<div>
    <div class="row">
        <table class="table table-striped table-sm">
            <thead>
            <th>Recipe</th>
            <th>Gold Cost</th>
            <th>XP</th>
{#            <th>Gold/XP</th>#}
            <th>XP/Gold</th>
            </thead>
            <tbody>
                <recipe
                        v-for="data, name in recipes"
                        v-bind:key="name"
                        v-bind:data="data"
                        v-bind:name="name"
                >
                </recipe>
            </tbody>
        </table>
    </div>
</div>