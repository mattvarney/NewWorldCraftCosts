<div>
    <div class="row">
        <table class="table table-striped table-sm">
            <thead>
            <th>Recipe</th>
            <th>Gold Cost<br /><small>incl. salvaging</small></th>
            <th>XP</th>
{#            <th>Gold/XP</th>#}
            <th>XP/Gold</th>
            </thead>
            <tbody>
                <recipe
                        v-for="recipe in recipes"
                        v-bind:key="recipe.name"
                        v-bind:recipe="recipe"
                >
                </recipe>
            </tbody>
        </table>
    </div>
</div>