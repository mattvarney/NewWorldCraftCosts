<div>
    <div class="row">
        <table class="table table-striped table-sm">
            <thead>
            <th>Resource</th>
            <th>Gold Cost</th>
            </thead>
            <tbody>
                <resource
                        v-for="data, key in resources"
                        v-bind:key="key"
                        v-bind:resourcekey="key"
                        v-bind:data="data"
                        @update-resource-cost="updateResourceCost($event)"
                >
                </resource>
            </tbody>
        </table>
    </div>
</div>