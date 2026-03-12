import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\MetalTransactionController::store
* @see app/Http/Controllers/MetalTransactionController.php:11
* @route '/metal-transactions'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/metal-transactions',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\MetalTransactionController::store
* @see app/Http/Controllers/MetalTransactionController.php:11
* @route '/metal-transactions'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\MetalTransactionController::store
* @see app/Http/Controllers/MetalTransactionController.php:11
* @route '/metal-transactions'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\MetalTransactionController::store
* @see app/Http/Controllers/MetalTransactionController.php:11
* @route '/metal-transactions'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\MetalTransactionController::store
* @see app/Http/Controllers/MetalTransactionController.php:11
* @route '/metal-transactions'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

const metalTransactions = {
    store: Object.assign(store, store),
}

export default metalTransactions