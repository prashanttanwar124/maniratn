import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\MortgageController::addPayment
* @see app/Http/Controllers/MortgageController.php:75
* @route '/mortgages/{mortgage}/payment'
*/
export const addPayment = (args: { mortgage: number | { id: number } } | [mortgage: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: addPayment.url(args, options),
    method: 'post',
})

addPayment.definition = {
    methods: ["post"],
    url: '/mortgages/{mortgage}/payment',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\MortgageController::addPayment
* @see app/Http/Controllers/MortgageController.php:75
* @route '/mortgages/{mortgage}/payment'
*/
addPayment.url = (args: { mortgage: number | { id: number } } | [mortgage: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { mortgage: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { mortgage: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            mortgage: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        mortgage: typeof args.mortgage === 'object'
        ? args.mortgage.id
        : args.mortgage,
    }

    return addPayment.definition.url
            .replace('{mortgage}', parsedArgs.mortgage.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\MortgageController::addPayment
* @see app/Http/Controllers/MortgageController.php:75
* @route '/mortgages/{mortgage}/payment'
*/
addPayment.post = (args: { mortgage: number | { id: number } } | [mortgage: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: addPayment.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\MortgageController::addPayment
* @see app/Http/Controllers/MortgageController.php:75
* @route '/mortgages/{mortgage}/payment'
*/
const addPaymentForm = (args: { mortgage: number | { id: number } } | [mortgage: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: addPayment.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\MortgageController::addPayment
* @see app/Http/Controllers/MortgageController.php:75
* @route '/mortgages/{mortgage}/payment'
*/
addPaymentForm.post = (args: { mortgage: number | { id: number } } | [mortgage: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: addPayment.url(args, options),
    method: 'post',
})

addPayment.form = addPaymentForm

/**
* @see \App\Http\Controllers\MortgageController::index
* @see app/Http/Controllers/MortgageController.php:16
* @route '/mortgages'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/mortgages',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\MortgageController::index
* @see app/Http/Controllers/MortgageController.php:16
* @route '/mortgages'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\MortgageController::index
* @see app/Http/Controllers/MortgageController.php:16
* @route '/mortgages'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\MortgageController::index
* @see app/Http/Controllers/MortgageController.php:16
* @route '/mortgages'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\MortgageController::index
* @see app/Http/Controllers/MortgageController.php:16
* @route '/mortgages'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\MortgageController::index
* @see app/Http/Controllers/MortgageController.php:16
* @route '/mortgages'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\MortgageController::index
* @see app/Http/Controllers/MortgageController.php:16
* @route '/mortgages'
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
* @see \App\Http\Controllers\MortgageController::store
* @see app/Http/Controllers/MortgageController.php:28
* @route '/mortgages'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/mortgages',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\MortgageController::store
* @see app/Http/Controllers/MortgageController.php:28
* @route '/mortgages'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\MortgageController::store
* @see app/Http/Controllers/MortgageController.php:28
* @route '/mortgages'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\MortgageController::store
* @see app/Http/Controllers/MortgageController.php:28
* @route '/mortgages'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\MortgageController::store
* @see app/Http/Controllers/MortgageController.php:28
* @route '/mortgages'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\MortgageController::update
* @see app/Http/Controllers/MortgageController.php:62
* @route '/mortgages/{mortgage}'
*/
export const update = (args: { mortgage: number | { id: number } } | [mortgage: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '/mortgages/{mortgage}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\MortgageController::update
* @see app/Http/Controllers/MortgageController.php:62
* @route '/mortgages/{mortgage}'
*/
update.url = (args: { mortgage: number | { id: number } } | [mortgage: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { mortgage: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { mortgage: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            mortgage: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        mortgage: typeof args.mortgage === 'object'
        ? args.mortgage.id
        : args.mortgage,
    }

    return update.definition.url
            .replace('{mortgage}', parsedArgs.mortgage.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\MortgageController::update
* @see app/Http/Controllers/MortgageController.php:62
* @route '/mortgages/{mortgage}'
*/
update.put = (args: { mortgage: number | { id: number } } | [mortgage: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\MortgageController::update
* @see app/Http/Controllers/MortgageController.php:62
* @route '/mortgages/{mortgage}'
*/
update.patch = (args: { mortgage: number | { id: number } } | [mortgage: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\MortgageController::update
* @see app/Http/Controllers/MortgageController.php:62
* @route '/mortgages/{mortgage}'
*/
const updateForm = (args: { mortgage: number | { id: number } } | [mortgage: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\MortgageController::update
* @see app/Http/Controllers/MortgageController.php:62
* @route '/mortgages/{mortgage}'
*/
updateForm.put = (args: { mortgage: number | { id: number } } | [mortgage: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\MortgageController::update
* @see app/Http/Controllers/MortgageController.php:62
* @route '/mortgages/{mortgage}'
*/
updateForm.patch = (args: { mortgage: number | { id: number } } | [mortgage: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

update.form = updateForm

const MortgageController = { addPayment, index, store, update }

export default MortgageController