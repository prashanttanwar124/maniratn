import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\DashboardController::index
* @see app/Http/Controllers/DashboardController.php:21
* @route '/dashboard'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/dashboard',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\DashboardController::index
* @see app/Http/Controllers/DashboardController.php:21
* @route '/dashboard'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\DashboardController::index
* @see app/Http/Controllers/DashboardController.php:21
* @route '/dashboard'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\DashboardController::index
* @see app/Http/Controllers/DashboardController.php:21
* @route '/dashboard'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\DashboardController::index
* @see app/Http/Controllers/DashboardController.php:21
* @route '/dashboard'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\DashboardController::index
* @see app/Http/Controllers/DashboardController.php:21
* @route '/dashboard'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\DashboardController::index
* @see app/Http/Controllers/DashboardController.php:21
* @route '/dashboard'
*/
indexForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

index.form = indexForm

/**
* @see \App\Http\Controllers\DashboardController::openDay
* @see app/Http/Controllers/DashboardController.php:124
* @route '/dashboard/open-day'
*/
export const openDay = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: openDay.url(options),
    method: 'post',
})

openDay.definition = {
    methods: ["post"],
    url: '/dashboard/open-day',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\DashboardController::openDay
* @see app/Http/Controllers/DashboardController.php:124
* @route '/dashboard/open-day'
*/
openDay.url = (options?: RouteQueryOptions) => {
    return openDay.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\DashboardController::openDay
* @see app/Http/Controllers/DashboardController.php:124
* @route '/dashboard/open-day'
*/
openDay.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: openDay.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\DashboardController::openDay
* @see app/Http/Controllers/DashboardController.php:124
* @route '/dashboard/open-day'
*/
const openDayForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: openDay.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\DashboardController::openDay
* @see app/Http/Controllers/DashboardController.php:124
* @route '/dashboard/open-day'
*/
openDayForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: openDay.url(options),
    method: 'post',
})

openDay.form = openDayForm

/**
* @see \App\Http\Controllers\DashboardController::closeDay
* @see app/Http/Controllers/DashboardController.php:148
* @route '/dashboard/close-day'
*/
export const closeDay = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: closeDay.url(options),
    method: 'post',
})

closeDay.definition = {
    methods: ["post"],
    url: '/dashboard/close-day',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\DashboardController::closeDay
* @see app/Http/Controllers/DashboardController.php:148
* @route '/dashboard/close-day'
*/
closeDay.url = (options?: RouteQueryOptions) => {
    return closeDay.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\DashboardController::closeDay
* @see app/Http/Controllers/DashboardController.php:148
* @route '/dashboard/close-day'
*/
closeDay.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: closeDay.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\DashboardController::closeDay
* @see app/Http/Controllers/DashboardController.php:148
* @route '/dashboard/close-day'
*/
const closeDayForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: closeDay.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\DashboardController::closeDay
* @see app/Http/Controllers/DashboardController.php:148
* @route '/dashboard/close-day'
*/
closeDayForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: closeDay.url(options),
    method: 'post',
})

closeDay.form = closeDayForm

/**
* @see \App\Http\Controllers\DashboardController::updateRates
* @see app/Http/Controllers/DashboardController.php:107
* @route '/dashboard/update-rates'
*/
export const updateRates = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: updateRates.url(options),
    method: 'post',
})

updateRates.definition = {
    methods: ["post"],
    url: '/dashboard/update-rates',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\DashboardController::updateRates
* @see app/Http/Controllers/DashboardController.php:107
* @route '/dashboard/update-rates'
*/
updateRates.url = (options?: RouteQueryOptions) => {
    return updateRates.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\DashboardController::updateRates
* @see app/Http/Controllers/DashboardController.php:107
* @route '/dashboard/update-rates'
*/
updateRates.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: updateRates.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\DashboardController::updateRates
* @see app/Http/Controllers/DashboardController.php:107
* @route '/dashboard/update-rates'
*/
const updateRatesForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: updateRates.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\DashboardController::updateRates
* @see app/Http/Controllers/DashboardController.php:107
* @route '/dashboard/update-rates'
*/
updateRatesForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: updateRates.url(options),
    method: 'post',
})

updateRates.form = updateRatesForm

/**
* @see \App\Http\Controllers\DashboardController::addFunds
* @see app/Http/Controllers/DashboardController.php:0
* @route '/dashboard/add-funds'
*/
export const addFunds = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: addFunds.url(options),
    method: 'post',
})

addFunds.definition = {
    methods: ["post"],
    url: '/dashboard/add-funds',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\DashboardController::addFunds
* @see app/Http/Controllers/DashboardController.php:0
* @route '/dashboard/add-funds'
*/
addFunds.url = (options?: RouteQueryOptions) => {
    return addFunds.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\DashboardController::addFunds
* @see app/Http/Controllers/DashboardController.php:0
* @route '/dashboard/add-funds'
*/
addFunds.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: addFunds.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\DashboardController::addFunds
* @see app/Http/Controllers/DashboardController.php:0
* @route '/dashboard/add-funds'
*/
const addFundsForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: addFunds.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\DashboardController::addFunds
* @see app/Http/Controllers/DashboardController.php:0
* @route '/dashboard/add-funds'
*/
addFundsForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: addFunds.url(options),
    method: 'post',
})

addFunds.form = addFundsForm

const DashboardController = { index, openDay, closeDay, updateRates, addFunds }

export default DashboardController