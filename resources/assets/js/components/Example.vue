<template>

    <div>

        <form class="form-inline" v-on:submit.prevent="parsePage">
            
            <div class="form-group">
                <label for="page">Url</label>
                <input type="text" class="form-control" v-model="pageUrl" placeholder="url">
            </div>

            <div class="form-group">
                <label for="page">Min. m2 pris</label>
                <input type="text" class="form-control" v-model="minKrm2" placeholder="m2 pris">
            </div>

            <!-- <div class="form-group">
                <label for="page">From</label>
                <input type="text" class="form-control" v-model="dateFrom" placeholder="date">
            </div> -->

            <button type="submit" class="btn btn-default">Søg</button>

            <button type="button" class="btn btn-link" @click="loadBorups">Borups</button>
            <button type="button" class="btn btn-link" @click="loadFjenne">Fjenne</button>
        </form>

        <br>

        <table class="table" v-if="floorRegression && aboveRegression && allRegression">
            <thead>
                <tr>
                    <th>Periode</th>
                    <th>Alle</th>
                    <th>Etage 0</th>
                    <th>Etage 1+</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Daglig ændring (kr)</td>
                    <td>{{ allRegression.m | avgDaily }}</td>
                    <td>{{ floorRegression.m | avgDaily }}</td>
                    <td>{{ aboveRegression.m | avgDaily }}</td>
                </tr>
                <tr>
                    <td>Månedlig ændring (kr)</td>
                    <td>{{ allRegression.m | avgMonthly }}</td>
                    <td>{{ floorRegression.m | avgMonthly }}</td>
                    <td>{{ aboveRegression.m | avgMonthly }}</td>
                </tr>
            </tbody>
        </table>

        <div id="chart" style="height:500px"></div>

        <br>

        <table-component 
            table-class="table"
            :data="pageData"
            sort-by="date"
            sort-order="asc"
            :show-caption="false"
        >
            <table-column label="" :sortable="false" :filterable="false">
                 <template scope="row">
                    <input type="checkbox" :checked="row.selected" @change="clickCheckbox(row)">
                 </template>
             </table-column>

            <table-column show="address" label="Adresse"></table-column>
            <table-column show="dateStr" label="Dato" data-type="date:DD-MM-YYYY"></table-column>
            <table-column show="price" label="Pris" data-type="numeric"></table-column>
            <table-column show="krm2" label="Kr/m2" data-type="numeric"></table-column>
            <table-column show="rooms" label="Værelser" data-type="numeric"></table-column>
            <table-column show="m2" label="m2" data-type="numeric"></table-column>
            <table-column show="floor" label="Etage" data-type="numeric"></table-column>
                          
        </table-component>
    
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
                }],
                pageData: [],
                minKrm2: 15000,
                dateFrom: '',
                pageUrl: 'http://www.boliga.dk/solgt/ejerlejlighed-Borups%20All%C3%A9-2400'
            }
        },
        filters: {
            avgDaily(val) {
                return (val * 1000 * 60 * 60 * 24).toFixed(1)
            },
            avgMonthly(val) {
                return (val * 1000 * 60 * 60 * 24 * 30).toFixed(1)
            }
        },
        methods: {

            loadFjenne() {
                this.pageUrl = 'http://www.boliga.dk/salg/resultater?so=1&sort=omregnings_dato-d&maxsaledate=today&iPostnr=2700&gade=Fjenneslevvej&type=&minsaledate=2016'
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

            parsePage() {

                let form = {
                    pageUrl: this.pageUrl,
                    minKrm2: this.minKrm2                    
                }

                if (this.dateFrom) {
                    form.dateFrom = this.dateFrom
                }

                axios.post('parsedom', form).then(response => {
                    
                    this.pageData = response.data

                    this.makeSeries()                    

                    this.makeChart()
                })
            },
            makeRegression(data) {

                // let x = data.map(d => d.date)
                // let y = data.map(d => d.krm2)

                // const params = new SimpleLinearRegression(x, y)

                let minDate = _.min(data, x => x.date).date

                let regrdata = data.map(d => [d.date - minDate, parseInt(d.krm2)])

                const params = ss.linearRegression(regrdata)
                
                return params
            },
            makeSeries() {

                let allData = this.pageData.filter(x => x.selected)
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

                let floorData = this.pageData.filter(x => x.floor == 0 && x.selected)
                let minFloorData = _.min(floorData, x => x.date).date
                this.floorRegression = this.makeRegression(floorData)
                const floorXY = [
                    {
                        x: floorData[0].date, 
                        y: (this.floorRegression.m * (floorData[0].date - minFloorData) + this.floorRegression.b)
                    },
                    {
                        x: floorData[floorData.length-1].date, 
                        y: (this.floorRegression.m * (floorData[floorData.length - 1].date - minFloorData) + this.floorRegression.b)
                    }
                ]

                let aboveData = this.pageData.filter(x => x.floor > 0 && x.selected)
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

                this.series[0].data = this.makeData(allData, null)
                this.series[1].data = this.makeData(aboveData, -1)
                this.series[2].data = this.makeData(floorData, 0)
                this.series[3].data = allXY
                this.series[4].data = aboveXY
                this.series[5].data = floorXY
            },
            makeData(data, floor) {

                let newdata = []

                if (floor == null) {
                    newdata = this.pageData.filter(x => x.selected)                    
                }

                else if (floor >= 0) {
                    newdata = this.pageData.filter(x => x.floor == floor && x.selected)
                }

                else if (floor < 0) {
                    newdata = this.pageData.filter(x => x.floor > 0 && x.selected)
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
                        m2: d.m2
                    }
                })

            },
            makeChart () {
                new Highcharts.Chart({
                    chart: {
                        renderTo: 'chart', 
                        zoomType: 'xy'                  
                    },
                    title: {
                        text: 'm2/pris udvikling'
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
                        pointFormat: '<ul> <li>Pris: {point.price}<li> <li>Kr/m2: {point.y}<li> <li>Adresse: {point.address}<li> <li>Etage: {point.floor}<li> <li>M2: {point.m2}<li> <li>Dato: {point.dateStr}<li> </ul>'
                    },
                    series: this.series,
                    credits: {
                        enabled: false
                    }
                });
            },
        },
        mounted() {
            

        }
    }
</script>
