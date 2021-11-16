{% extends "layouts/main.volt" %}

{% block content %}

    {{ super() }}
    <style>
        .container{
            max-width:1600px;
        }
        .btn.disabled, .btn:disabled{
            opacity: .15;
        }
        .btn-group-xs > .btn, .btn-xs {
            padding: .30rem .6rem;
            font-size: .75rem;
            line-height: .5;
            border-radius: .2rem;
        }

        ul.senatorList li {
            line-height: 1.2em;
            border:1px solid black;
            width:100%;
            padding:3px;
            margin-top:3px;
            margin-bottom:3px;
            white-space:nowrap;
        }
        ul.senatorList {
            list-style-type: none;
            column-count: 5;
            column-gap: 10px;
            padding-bottom:10px;
        }
        .upcomingNominationSenatorList {
            border:1px solid black;
            padding:3px;
            margin:3px;
            white-space:nowrap;
        }
    </style>
    <h2>New World Craft XP Calculator</h2>
    <div class="container">
        <div class="row">
            <div id="demo">
                <ul class="nav nav-pills">
                    <li
                        v-for="trade in trades"
                        v-bind:key="trade"
                        v-bind:trade="trade"
                        class="nav-item"
                    >
                        <a v-bind:class="{ active: trade == activeTrade }" class="nav-link" @click="changeActiveTrade(trade)">[[trade]]</a>
                    </li>
                </ul>

                <div class="float-left mr-5">
                    <recipes
                            :recipes="recipes[activeTrade]"
                            :resources="resources"
                    ></recipes>
                </div>
                <div class="float-right ml-5">
                    <resources
                            :resources="resourcesToShow"
                            @update-resource-cost="updateResourceCost($event)"
                    ></resources>
                </div>
            </div>
        </div>
    </div>
    <script>
        Vue.mixin({ delimiters: ['[[',']]'] });
        Vue.config.devtools = true;

        Vue.component("recipe", {
            template: `{{ partial("index/partials/recipe") }}`,
            props: {
                recipe: Object,
                name: String,
            },
            computed: {
                cost: function () {
                    let cost = 0;
                   for (let i = 0; i < this.recipe.ingredients.length; i++) {
                       let resource = this.recipe.ingredients[i].resource;
                      cost += this.$root.resources[resource].cost * this.recipe.ingredients[i].count;
                   }
                   return +cost.toFixed(2);
                },
                totalCost: function() {
                    let totalCost =  this.cost - this.salvageCost;
                    return +totalCost.toFixed(2);
                },
                xpPerGold: function() {
                    let xpPerGold =  this.recipe.xp / this.totalCost;
                    return +xpPerGold.toFixed(2);
                },
                tooltip: function() {
                    let salvageRange = this.salvageRange;
                    let tooltip =  "Table: " + this.capitalize(this.recipe.craftTable) + " Tier " + this.recipe.craftTableTier + "\n"
                        + "Skill Required: " + this.recipe.skillRequired + "\n"
                        + "Item Type: " + this.capitalize(this.recipe.itemType) + "\n";
                        + "Resource Cost: " + this.cost + " gold\n";
                    if (salvageRange.min != 0 && salvageRange.max != 0) {
                        tooltip += "Salvages To: " + salvageRange.min + "-" + salvageRange.max + " " + this.$root.resources[this.recipe.ingredients[0].resource].name + "\n"
                    }
                    tooltip += "Salvage Value: " + this.salvageCost + " gold\n";
                    tooltip += "-------\n";
                    for (let i = 0; i < this.recipe.ingredients.length; i++) {
                        tooltip += this.$root.resources[this.recipe.ingredients[i].resource].name + " x" + this.recipe.ingredients[i].count + "\n";
                    }
                    return tooltip;
                },
                salvageCost: function() {
                    let salvageItem = this.recipe.ingredients[0].resource;
                    let salvageItemCost = this.$root.resources[salvageItem].cost;
                    let salvageRange = this.salvageRange;
                    let salvageCost =  salvageItemCost * salvageRange.predicted;
                    return +salvageCost.toFixed(2);
                },
                salvageRange: function() {
                    let oneThroughFourTypes = ["greataxe", "hammer", "firestaff", "lifestaff", "fishingpole", "chest"];
                    let oneThroughThreeTypes = ["pants", "spear", "bow", "musket", "pickaxe", "woodaxe"];
                    let oneThroughTwoTypes = ["rapier", "sword", "shield", "head", "gloves", "boots", "hatchet", "icegauntlet", "sickle", "skinningknife"];
                    let noSalvage = ["resource", "consumable", "furniture"];
                    if (oneThroughFourTypes.includes(this.recipe.itemType)) {
                        return {"min": 1, "max": 4, "predicted": 2.13};   //213 when crafting 100
                    }
                    if (oneThroughThreeTypes.includes(this.recipe.itemType)) {
                        return {"min": 1, "max": 3, "predicted": 1.40};
                    }
                    if (oneThroughTwoTypes.includes(this.recipe.itemType)) {
                        return {"min": 1, "max": 2, "predicted": 1.25};
                    }
                    if (noSalvage.includes(this.recipe.itemType)) {
                        return {"min": 0, "max": 0, "predicted": 0};
                    }
                }
            },
            methods: {
                capitalize: function (s) {
                    return s[0].toUpperCase() + s.slice(1);
                }
            }
        });

        Vue.component("recipes", {
            template: `{{ partial("index/partials/recipes") }}`,
            props: {
                recipes: Array,
            },
            computed: {

                // sortedRecipes() {
                //     return this.recipes.sort((a, b) => { return b.totalCost - a.totalCost;});
                // }
            },
            methods: {
                updateResourceCost: function (data) {
                    console.log(data);
                }
            }
        });

        Vue.component("resource", {
            template: `{{ partial("index/partials/resource") }}`,
            props: {
                data: Object,
                resourcekey: String
            },
            computed: {

            },
            methods: {
                updateResourceCost: function () {
                    this.$emit("update-resource-cost", {resource: this.resourcekey, cost: this.data.cost});
                }
            }
        });

        Vue.component("resources", {
            template: `{{ partial("index/partials/resources") }}`,
            props: {
                resources: Object,
            },
            computed: {

            },
            methods: {
                updateResourceCost: function (data) {
                    this.$emit("update-resource-cost", data);
                }
            }
        });

        var demo = new Vue({
            el: '#demo',
            data: {
                settings: {

                },
                recipes: {{ recipes }},
                resources: {{ resources }},
                trades: ["Engineering", "Furnishing", "Armoring", "Weaponsmithing"],
                activeTrade: "Engineering"
            },
            mounted: function () { },
            computed: {
                resourcesToShow: function () {
                    let resourcesToShow = {};
                    let activeTradeRecipes = this.recipes[this.activeTrade];
                    for (const key in activeTradeRecipes) {
                        for (let i = 0; i < activeTradeRecipes[key].ingredients.length; i++) {
                            resourcesToShow[activeTradeRecipes[key].ingredients[i].resource] = this.$root.resources[activeTradeRecipes[key].ingredients[i].resource];
                        }
                    }
                    return resourcesToShow;
                },
            },
            methods: {
                updateResourceCost: function (data) {
                    this.resources[data.resource].cost = data.cost;
                },
                changeActiveTrade: function (trade) {
                    this.activeTrade = trade;
                }

            },
        })

    </script>
{% endblock %}