<template>

    <div>

        <form class="form" v-on:submit.prevent="submitForm" >

            
            <!-- <div class="form-group">                 -->
                <input type="checkbox" v-model="useUrl"> Benyt direkte link fra Boliga
            <!-- </div> -->
                        
            <div v-if="useUrl">
                <div class="form-group">
                    <label for="page">
                        Link
                    </label>
                    <input type="text" class="form-control" v-model="pageUrl" placeholder="link" style="width:250px">
                    
                    <button type="button" class="btn btn-link" @click="loadBorups">Borups</button>
                    <button type="button" class="btn btn-link" @click="loadFjenne">Fjenne</button>
                </div>
            </div>

            <div v-else>
                <div class="form-group">
                    <label for="page">Vejnavn</label>
                    <input type="text" class="form-control" v-model="street" placeholder="vejnavn" style="width:250px">
                </div>

                <div class="form-group">
                    <label for="page">Postnummer</label>
                    <input type="text" class="form-control" v-model="zip" placeholder="postnummer" style="width:100px">
                </div>
            </div>
            
            <div class="form-group">
                <label for="page">Min. m2 pris</label>
                <input type="text" class="form-control" v-model="minKrm2" placeholder="m2 pris" style="width:100px">
            </div>

            <div class="form-group">
                <label for="page">Antal dage tilbage</label>
                <input type="text" class="form-control" v-model="daysBack" placeholder="dage" style="width:100px">
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-default">Søg</button>
            </div>            
            
        </form>

        <br>

        <table class="table" v-if="floorRegression && aboveRegression && allRegression">
            <thead>
                <tr>
                    <th>M2 <input type="text" v-model="selectM2" placeholder="m2"></th>
                    <th>Alle</th>
                    <th>Etage 0</th>
                    <th>Etage 1+</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Daglig ændring (kr)</td>
                    <td>{{ (allRegression.m * selectM2) | avgDaily }}</td>
                    <td>{{ (floorRegression.m * selectM2) | avgDaily }}</td>
                    <td>{{ (aboveRegression.m * selectM2) | avgDaily }}</td>                    
                </tr>
                <tr>
                    <td>Månedlig ændring (kr)</td>
                    <td>
                        <strong>{{ (allRegression.m * selectM2) | avgMonthly }}</strong>
                    </td>
                    <td>{{ (floorRegression.m * selectM2) | avgMonthly }}</td>
                    <td>{{ (aboveRegression.m * selectM2) | avgMonthly }}</td>                    
                </tr>
            </tbody>
        </table>

        <!-- <div>
            <input type="checkbox" v-model="useSaleInRegression">
        </div> -->

        <div id="chart" style="height:500px"></div>

        <br>

        <div class="table-responsive">

            <table-component v-if="showTable"
                table-class="table"
                :data="pageData"
                sort-by="date"
                sort-order="desc"
                :show-caption="false"
            >
                <table-column label="" :sortable="false" :filterable="false">
                    <template scope="row">
                        <input type="checkbox" :checked="row.selected" @change="clickCheckbox(row)">
                    </template>
                </table-column>

                <table-column show="sold" label="Solgt"></table-column>
                
                <table-column show="address" label="Adresse">
                    <template scope="row">
                        <a :href="row.link" v-if="row.link" v-html="row.address"></a>
                        <span v-else v-html="row.address"></span>
                    </template>                
                </table-column>

                <table-column show="dateStr" label="Dato" data-type="date:DD-MM-YYYY"></table-column>
                <table-column show="price" label="Pris" data-type="numeric"></table-column>
                <table-column show="krm2" label="Kr/m2" data-type="numeric"></table-column>
                <table-column show="rooms" label="Værelser" data-type="numeric"></table-column>
                <table-column show="m2" label="m2" data-type="numeric"></table-column>
                <table-column show="floor" label="Etage" data-type="numeric"></table-column>
                              
            </table-component>

        </div>
    
    </div>
    
    
</template>

<script>

    import moment from 'moment'
    var Highcharts = require('highcharts')
    import ss from 'simple-statistics'

    import { TableComponent, TableColumn } from 'vue-table-component'

    export default {
        components: {
            TableColumn, TableComponent
        },
        data() {
            return {
                daysBack: 365*2,
                useUrl: true,
                street: '',
                zip: '',
                chart: null,
                showTable: true,
                useSaleInRegression: false,
                selectM2: 1,
                floorRegression: null,
                aboveRegression: null,
                allRegression: null,
                series: [{
                    type: 'scatter',
                    name: 'Alle',                    
                }, {
                    type: 'scatter',
                    name: 'Etage 1+',                    
                }, {
                    type: 'scatter',
                    name: 'Etage 0',                    
                }, {
                    type: 'line',
                    name: 'Alle etager regr.'
                }, {
                    type: 'line',
                    name: 'Etage 1+ regr.'
                }, {
                    type: 'line',
                    name: 'Etage 0 regr.'
                }, {
                    type: 'scatter',
                    name: 'Til salg'
                }],
                pageData: [],
                minKrm2: 15000,
                dateFrom: '',
                pageUrl: 'http://www.boliga.dk/solgt/ejerlejlighed-Borups%20All%C3%A9-2400'
            }
        },
        filters: {
            avgDaily(val) {
                return (val * 1000 * 60 * 60 * 24).toFixed(0)
            },
            avgMonthly(val) {
                return (val * 1000 * 60 * 60 * 24 * 30).toFixed(0)
            }
        },
        methods: {

            loadFjenne() {
                // this.pageUrl = 'http://www.boliga.dk/salg/resultater?so=1&sort=omregnings_dato-d&maxsaledate=today&iPostnr=2700&gade=Fjenneslevvej&type=&minsaledate=2016'

                this.pageUrl = 'http://www.boliga.dk/solgt/ejerlejlighed-Fjenneslevvej-2700'
            },

            loadBorups() {
                this.pageUrl = 'http://www.boliga.dk/solgt/ejerlejlighed-Borups%20All%C3%A9-2400'
            },

            clickCheckbox(col, index) {
                col.selected = !col.selected

                // console.log(this.pageData[0])

                this.makeSeries()

                this.makeChart()
            },

            submitForm() {

                let form = {
                    minKrm2: this.minKrm2,
                    daysBack: this.daysBack
                }

                if (this.useUrl) {
                    form.pageUrl = this.pageUrl
                } else {
                    form.pageUrl = 'http://www.boliga.dk/solgt/ejerlejlighed-' + escape(this.street) + '-' + this.zip
                }

                
                axios.post('parse', form).then(response => {

                    this.showTable = false

                    this.pageData = response.data

                    if (this.pageData.length > 0) {

                        this.pageData = _.sortBy(this.pageData, d => d.date)

                        this.makeSeries()

                        this.showTable = true                    
                    }
                    
                })                
            },

            loadData(form) {



                axios.post('parsesold', form).then(response => {
                
                    this.showTable = false

                    this.pageData = response.data

                    if (this.pageData.length > 0) {

                        let formSale = {
                            pageUrl: form.pageUrl.replace('solgt/', '')
                        }

                        axios.post('parsesale', formSale).then(response2 => {
                            let data = response2.data

                            _.each(data, d => this.pageData.push(d))

                            this.pageData = _.sortBy(this.pageData, x => x.sale)

                            this.makeSeries()

                            this.showTable = true
                        })    
                    }
                    
                })
            },

            loadFromStreet() {

                let form = {
                    pageUrl: 'http://www.boliga.dk/solgt/ejerlejlighed-' + escape(this.street) + '-' + this.zip,
                    minKrm2: this.minKrm2
                }

                this.loadData(form)

            },

            loadFromUrl() {

                let form = {
                    pageUrl: this.pageUrl,
                    minKrm2: this.minKrm2                    
                }

                this.loadData(form)

                
            },
            makeRegression(data) {

                let minDate = _.min(data, x => x.date).date

                let regrdata = data.map(d => [d.date - minDate, parseInt(d.krm2)])

                const params = ss.linearRegression(regrdata)
                
                return params
            },
            makeSeries() {

                let allData = this.pageData.filter(x => x.selected && x.sold == 'Ja')
                let minAllData = _.min(allData, x => x.date).date
                this.allRegression = this.makeRegression(allData)
                const allXY = [
                    {
                        x: allData[0].date, 
                        y: (this.allRegression.m * (allData[0].date - minAllData) + this.allRegression.b)
                    },
                    {
                        x: allData[allData.length-1].date, 
                        y: (this.allRegression.m * (allData[allData.length - 1].date - minAllData) + this.allRegression.b)
                    }
                ]

                let floorData = this.pageData.filter(x => x.floor == 0 && x.selected && x.sold == 'Ja')
                let minFloorData = _.min(floorData, x => x.date).date
                this.floorRegression = this.makeRegression(floorData)
                const floorXY = [                
                    {
                        x: parseInt(floorData[0].date), 
                        y: (this.floorRegression.m * (floorData[0].date - minFloorData) + this.floorRegression.b)
                    },
                    {
                        x: parseInt(floorData[floorData.length-1].date), 
                        y: (this.floorRegression.m * (floorData[floorData.length - 1].date - minFloorData) + this.floorRegression.b)
                    }
                ]

                let aboveData = this.pageData.filter(x => x.floor > 0 && x.selected && x.sold == 'Ja')
                let minAboveData = _.min(aboveData, x => x.date).date
                this.aboveRegression = this.makeRegression(aboveData)
                const aboveXY = [
                    {
                        x: aboveData[0].date, 
                        y: (this.aboveRegression.m * (aboveData[0].date - minAboveData) + this.aboveRegression.b)
                    },
                    {
                        x: aboveData[aboveData.length-1].date, 
                        y: (this.aboveRegression.m * (aboveData[aboveData.length - 1].date - minAboveData) + this.aboveRegression.b)
                    }
                ]

                // console.log(this.floorRegression, this.aboveRegression)

                this.chart.series[0].update({ data: this.makeData(allData, null, 'Ja') })
                this.chart.series[1].update({ data: this.makeData(aboveData, -1, 'Ja') })
                this.chart.series[2].update({ data: this.makeData(floorData, 0, 'Ja') })
                this.chart.series[3].update({ data: allXY })
                this.chart.series[4].update({ data: aboveXY })
                this.chart.series[5].update({ data: floorXY })
                this.chart.series[6].update({ data: this.makeData(this.pageData.filter(x => x.sold == 'Nej' && x.selected), null, 'Nej') })
                this.chart.series[6].update({ 
                    events: {
                        click: function (event) {
                            var win = window.open(event.point.link, '_blank');
                            win.focus();
                        }
                    }
                 })
            },
            makeData(data, floor, sold) {

                let newdata = []

                if (floor == null) {
                    newdata = this.pageData.filter(x => x.selected && x.sold == sold)                    
                }

                else if (floor >= 0) {
                    newdata = this.pageData.filter(x => x.floor == floor && x.selected && x.sold == sold)
                }

                else if (floor < 0) {
                    newdata = this.pageData.filter(x => x.floor > 0 && x.selected && x.sold == sold)
                }

                return newdata.map(d => {
                    return {
                        // x: moment(d.date, 'DD-MM-YYYY').unix(),
                        x: parseInt(d.date),
                        y: parseInt(d.krm2),
                        price: d.price / 1000,
                        address: d.address,
                        floor: d.floor,
                        rooms: d.rooms,
                        dateStr: d.dateStr,
                        m2: d.m2,
                        link: d.link
                    }
                })

            },
            makeChart () {
                this.chart = new Highcharts.Chart({
                    chart: {
                        renderTo: 'chart', 
                        zoomType: 'xy'                  
                    },
                    title: {
                        text: 'm2/pris udvikling'
                    },
                    subtitle: {
                        text: 'klik på de gule (til salg) for at åbne på boliga'
                    },
                    xAxis: {
                        type: 'datetime',
                        dateTimeLabelFormats: { // don't display the dummy year
                            month: '%e. %b',
                            year: '%b'
                        },
                    },
                    legend: {
                        enabled: true
                    },
                    yAxis: {
                        title: {
                            text: 'kr/m2'
                        }                        
                    },
                    plotOptions: {
                        series: {
                            dataLabels: {
                                enabled: true,
                                format: '{point.price}'
                            }
                        }
                    },
                    tooltip: {
                        useHTML: true,
                        pointFormat: '<ul> <li>Pris: {point.price}<li> <li>Kr/m2: {point.y}<li> <li>Adresse: {point.address}<li> <li>Etage: {point.floor}<li> <li>M2: {point.m2}<li> <li>Dato: {point.dateStr}<li></ul>'
                    },
                    series: this.series,
                    credits: {
                        enabled: false
                    }
                });
            },
        },
        mounted() {
            
            this.makeChart()
        }
    }
</script>
