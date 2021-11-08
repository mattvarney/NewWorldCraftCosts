
        <tr>
            <td>[[ data.name ]]</td>
            <td>
                <input v-model.number="data.cost" @change="updateResourceCost" class="form-control form-control-sm" type="number" step=".01" min="0"/>
            </td>
        </tr>
