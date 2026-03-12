import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\OrderController::transaction
* @see app/Http/Controllers/OrderController.php:0
* @route '/orders/{item}/transaction'
*/
export const transaction = (args: { item: string | number } | [item: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: transaction.url(args, options),
    method: 'post',
})

transaction.definition = {
    methods: ["post"],
    url: '/orders/{item}/transaction',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OrderController::transaction
* @see app/Http/Controllers/OrderController.php:0
* @route '/orders/{item}/transaction'
*/
transaction.url = (args: { item: string | number } | [item: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { item: args }
    }

    if (Array.isArray(args)) {
        args = {
            item: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        item: args.item,
    }

    return transaction.definition.url
            .replace('{item}', parsedArgs.item.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrderController::transaction
* @see app/Http/Controllers/OrderController.php:0
* @route '/orders/{item}/transaction'
*/
transaction.post = (args: { item: string | number } | [item: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: transaction.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OrderController::transaction
* @see app/Http/Controllers/OrderController.php:0
* @route '/orders/{item}/transaction'
*/
const transactionForm = (args: { item: string | number } | [item: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: transaction.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OrderController::transaction
* @see app/Http/Controllers/OrderController.php:0
* @route '/orders/{item}/transaction'
*/
transactionForm.post = (args: { item: string | number } | [item: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: transaction.url(args, options),
    method: 'post',
})

transaction.form = transactionForm

/**
* @see \App\Http\Controllers\OrderController::transaction
* @see app/Http/Controllers/OrderController.php:136
* @route '/orders/{orderItem}/transaction'
*/
export const transaction = (args: { orderItem: number | { id: number } } | [orderItem: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: transaction.url(args, options),
    method: 'post',
})

transaction.definition = {
    methods: ["post"],
    url: '/orders/{orderItem}/transaction',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OrderController::transaction
* @see app/Http/Controllers/OrderController.php:136
* @route '/orders/{orderItem}/transaction'
*/
transaction.url = (args: { orderItem: number | { id: number } } | [orderItem: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { orderItem: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { orderItem: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            orderItem: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        orderItem: typeof args.orderItem === 'object'
        ? args.orderItem.id
        : args.orderItem,
    }

    return transaction.definition.url
            .replace('{orderItem}', parsedArgs.orderItem.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrderController::transaction
* @see app/Http/Controllers/OrderController.php:136
* @route '/orders/{orderItem}/transaction'
*/
transaction.post = (args: { orderItem: number | { id: number } } | [orderItem: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: transaction.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OrderController::transaction
* @see app/Http/Controllers/OrderController.php:136
* @route '/orders/{orderItem}/transaction'
*/
const transactionForm = (args: { orderItem: number | { id: number } } | [orderItem: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: transaction.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OrderController::transaction
* @see app/Http/Controllers/OrderController.php:136
* @route '/orders/{orderItem}/transaction'
*/
transactionForm.post = (args: { orderItem: number | { id: number } } | [orderItem: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: transaction.url(args, options),
    method: 'post',
})

transaction.form = transactionForm

/**
* @see \App\Http\Controllers\OrderController::index
* @see app/Http/Controllers/OrderController.php:22
* @route '/orders'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/orders',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\OrderController::index
* @see app/Http/Controllers/OrderController.php:22
* @route '/orders'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrderController::index
* @see app/Http/Controllers/OrderController.php:22
* @route '/orders'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\OrderController::index
* @see app/Http/Controllers/OrderController.php:22
* @route '/orders'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\OrderController::index
* @see app/Http/Controllers/OrderController.php:22
* @route '/orders'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\OrderController::index
* @see app/Http/Controllers/OrderController.php:22
* @route '/orders'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\OrderController::index
* @see app/Http/Controllers/OrderController.php:22
* @route '/orders'
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
* @see \App\Http\Controllers\OrderController::create
* @see app/Http/Controllers/OrderController.php:0
* @route '/orders/create'
*/
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/orders/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\OrderController::create
* @see app/Http/Controllers/OrderController.php:0
* @route '/orders/create'
*/
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrderController::create
* @see app/Http/Controllers/OrderController.php:0
* @route '/orders/create'
*/
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\OrderController::create
* @see app/Http/Controllers/OrderController.php:0
* @route '/orders/create'
*/
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\OrderController::create
* @see app/Http/Controllers/OrderController.php:0
* @route '/orders/create'
*/
const createForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\OrderController::create
* @see app/Http/Controllers/OrderController.php:0
* @route '/orders/create'
*/
createForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\OrderController::create
* @see app/Http/Controllers/OrderController.php:0
* @route '/orders/create'
*/
createForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

create.form = createForm

/**
* @see \App\Http\Controllers\OrderController::store
* @see app/Http/Controllers/OrderController.php:48
* @route '/orders'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/orders',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OrderController::store
* @see app/Http/Controllers/OrderController.php:48
* @route '/orders'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrderController::store
* @see app/Http/Controllers/OrderController.php:48
* @route '/orders'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OrderController::store
* @see app/Http/Controllers/OrderController.php:48
* @route '/orders'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OrderController::store
* @see app/Http/Controllers/OrderController.php:48
* @route '/orders'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\OrderController::show
* @see app/Http/Controllers/OrderController.php:0
* @route '/orders/{order}'
*/
export const show = (args: { order: string | number } | [order: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/orders/{order}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\OrderController::show
* @see app/Http/Controllers/OrderController.php:0
* @route '/orders/{order}'
*/
show.url = (args: { order: string | number } | [order: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { order: args }
    }

    if (Array.isArray(args)) {
        args = {
            order: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        order: args.order,
    }

    return show.definition.url
            .replace('{order}', parsedArgs.order.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrderController::show
* @see app/Http/Controllers/OrderController.php:0
* @route '/orders/{order}'
*/
show.get = (args: { order: string | number } | [order: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\OrderController::show
* @see app/Http/Controllers/OrderController.php:0
* @route '/orders/{order}'
*/
show.head = (args: { order: string | number } | [order: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\OrderController::show
* @see app/Http/Controllers/OrderController.php:0
* @route '/orders/{order}'
*/
const showForm = (args: { order: string | number } | [order: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\OrderController::show
* @see app/Http/Controllers/OrderController.php:0
* @route '/orders/{order}'
*/
showForm.get = (args: { order: string | number } | [order: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\OrderController::show
* @see app/Http/Controllers/OrderController.php:0
* @route '/orders/{order}'
*/
showForm.head = (args: { order: string | number } | [order: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

show.form = showForm

/**
* @see \App\Http\Controllers\OrderController::edit
* @see app/Http/Controllers/OrderController.php:0
* @route '/orders/{order}/edit'
*/
export const edit = (args: { order: string | number } | [order: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/orders/{order}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\OrderController::edit
* @see app/Http/Controllers/OrderController.php:0
* @route '/orders/{order}/edit'
*/
edit.url = (args: { order: string | number } | [order: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { order: args }
    }

    if (Array.isArray(args)) {
        args = {
            order: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        order: args.order,
    }

    return edit.definition.url
            .replace('{order}', parsedArgs.order.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrderController::edit
* @see app/Http/Controllers/OrderController.php:0
* @route '/orders/{order}/edit'
*/
edit.get = (args: { order: string | number } | [order: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\OrderController::edit
* @see app/Http/Controllers/OrderController.php:0
* @route '/orders/{order}/edit'
*/
edit.head = (args: { order: string | number } | [order: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\OrderController::edit
* @see app/Http/Controllers/OrderController.php:0
* @route '/orders/{order}/edit'
*/
const editForm = (args: { order: string | number } | [order: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\OrderController::edit
* @see app/Http/Controllers/OrderController.php:0
* @route '/orders/{order}/edit'
*/
editForm.get = (args: { order: string | number } | [order: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\OrderController::edit
* @see app/Http/Controllers/OrderController.php:0
* @route '/orders/{order}/edit'
*/
editForm.head = (args: { order: string | number } | [order: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

edit.form = editForm

/**
* @see \App\Http\Controllers\OrderController::update
* @see app/Http/Controllers/OrderController.php:0
* @route '/orders/{order}'
*/
export const update = (args: { order: string | number } | [order: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '/orders/{order}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\OrderController::update
* @see app/Http/Controllers/OrderController.php:0
* @route '/orders/{order}'
*/
update.url = (args: { order: string | number } | [order: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { order: args }
    }

    if (Array.isArray(args)) {
        args = {
            order: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        order: args.order,
    }

    return update.definition.url
            .replace('{order}', parsedArgs.order.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrderController::update
* @see app/Http/Controllers/OrderController.php:0
* @route '/orders/{order}'
*/
update.put = (args: { order: string | number } | [order: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\OrderController::update
* @see app/Http/Controllers/OrderController.php:0
* @route '/orders/{order}'
*/
update.patch = (args: { order: string | number } | [order: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\OrderController::update
* @see app/Http/Controllers/OrderController.php:0
* @route '/orders/{order}'
*/
const updateForm = (args: { order: string | number } | [order: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OrderController::update
* @see app/Http/Controllers/OrderController.php:0
* @route '/orders/{order}'
*/
updateForm.put = (args: { order: string | number } | [order: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OrderController::update
* @see app/Http/Controllers/OrderController.php:0
* @route '/orders/{order}'
*/
updateForm.patch = (args: { order: string | number } | [order: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

update.form = updateForm

/**
* @see \App\Http\Controllers\OrderController::destroy
* @see app/Http/Controllers/OrderController.php:0
* @route '/orders/{order}'
*/
export const destroy = (args: { order: string | number } | [order: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/orders/{order}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\OrderController::destroy
* @see app/Http/Controllers/OrderController.php:0
* @route '/orders/{order}'
*/
destroy.url = (args: { order: string | number } | [order: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { order: args }
    }

    if (Array.isArray(args)) {
        args = {
            order: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        order: args.order,
    }

    return destroy.definition.url
            .replace('{order}', parsedArgs.order.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrderController::destroy
* @see app/Http/Controllers/OrderController.php:0
* @route '/orders/{order}'
*/
destroy.delete = (args: { order: string | number } | [order: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\OrderController::destroy
* @see app/Http/Controllers/OrderController.php:0
* @route '/orders/{order}'
*/
const destroyForm = (args: { order: string | number } | [order: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OrderController::destroy
* @see app/Http/Controllers/OrderController.php:0
* @route '/orders/{order}'
*/
destroyForm.delete = (args: { order: string | number } | [order: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

/**
* @see \App\Http\Controllers\OrderController::assign
* @see app/Http/Controllers/OrderController.php:94
* @route '/orders/{orderItem}/assign'
*/
export const assign = (args: { orderItem: number | { id: number } } | [orderItem: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: assign.url(args, options),
    method: 'post',
})

assign.definition = {
    methods: ["post"],
    url: '/orders/{orderItem}/assign',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OrderController::assign
* @see app/Http/Controllers/OrderController.php:94
* @route '/orders/{orderItem}/assign'
*/
assign.url = (args: { orderItem: number | { id: number } } | [orderItem: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { orderItem: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { orderItem: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            orderItem: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        orderItem: typeof args.orderItem === 'object'
        ? args.orderItem.id
        : args.orderItem,
    }

    return assign.definition.url
            .replace('{orderItem}', parsedArgs.orderItem.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrderController::assign
* @see app/Http/Controllers/OrderController.php:94
* @route '/orders/{orderItem}/assign'
*/
assign.post = (args: { orderItem: number | { id: number } } | [orderItem: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: assign.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OrderController::assign
* @see app/Http/Controllers/OrderController.php:94
* @route '/orders/{orderItem}/assign'
*/
const assignForm = (args: { orderItem: number | { id: number } } | [orderItem: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: assign.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OrderController::assign
* @see app/Http/Controllers/OrderController.php:94
* @route '/orders/{orderItem}/assign'
*/
assignForm.post = (args: { orderItem: number | { id: number } } | [orderItem: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: assign.url(args, options),
    method: 'post',
})

assign.form = assignForm

/**
* @see \App\Http\Controllers\OrderController::complete
* @see app/Http/Controllers/OrderController.php:192
* @route '/orders/{orderItem}/complete'
*/
export const complete = (args: { orderItem: number | { id: number } } | [orderItem: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: complete.url(args, options),
    method: 'post',
})

complete.definition = {
    methods: ["post"],
    url: '/orders/{orderItem}/complete',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OrderController::complete
* @see app/Http/Controllers/OrderController.php:192
* @route '/orders/{orderItem}/complete'
*/
complete.url = (args: { orderItem: number | { id: number } } | [orderItem: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { orderItem: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { orderItem: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            orderItem: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        orderItem: typeof args.orderItem === 'object'
        ? args.orderItem.id
        : args.orderItem,
    }

    return complete.definition.url
            .replace('{orderItem}', parsedArgs.orderItem.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrderController::complete
* @see app/Http/Controllers/OrderController.php:192
* @route '/orders/{orderItem}/complete'
*/
complete.post = (args: { orderItem: number | { id: number } } | [orderItem: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: complete.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OrderController::complete
* @see app/Http/Controllers/OrderController.php:192
* @route '/orders/{orderItem}/complete'
*/
const completeForm = (args: { orderItem: number | { id: number } } | [orderItem: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: complete.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OrderController::complete
* @see app/Http/Controllers/OrderController.php:192
* @route '/orders/{orderItem}/complete'
*/
completeForm.post = (args: { orderItem: number | { id: number } } | [orderItem: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: complete.url(args, options),
    method: 'post',
})

complete.form = completeForm

/**
* @see \App\Http\Controllers\OrderController::deliver
* @see app/Http/Controllers/OrderController.php:0
* @route '/orders/{orderItem}/deliver'
*/
export const deliver = (args: { orderItem: string | number } | [orderItem: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: deliver.url(args, options),
    method: 'post',
})

deliver.definition = {
    methods: ["post"],
    url: '/orders/{orderItem}/deliver',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OrderController::deliver
* @see app/Http/Controllers/OrderController.php:0
* @route '/orders/{orderItem}/deliver'
*/
deliver.url = (args: { orderItem: string | number } | [orderItem: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { orderItem: args }
    }

    if (Array.isArray(args)) {
        args = {
            orderItem: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        orderItem: args.orderItem,
    }

    return deliver.definition.url
            .replace('{orderItem}', parsedArgs.orderItem.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrderController::deliver
* @see app/Http/Controllers/OrderController.php:0
* @route '/orders/{orderItem}/deliver'
*/
deliver.post = (args: { orderItem: string | number } | [orderItem: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: deliver.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OrderController::deliver
* @see app/Http/Controllers/OrderController.php:0
* @route '/orders/{orderItem}/deliver'
*/
const deliverForm = (args: { orderItem: string | number } | [orderItem: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: deliver.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OrderController::deliver
* @see app/Http/Controllers/OrderController.php:0
* @route '/orders/{orderItem}/deliver'
*/
deliverForm.post = (args: { orderItem: string | number } | [orderItem: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: deliver.url(args, options),
    method: 'post',
})

deliver.form = deliverForm

const orders = {
    transaction: Object.assign(transaction, transaction),
    index: Object.assign(index, index),
    create: Object.assign(create, create),
    store: Object.assign(store, store),
    show: Object.assign(show, show),
    edit: Object.assign(edit, edit),
    update: Object.assign(update, update),
    destroy: Object.assign(destroy, destroy),
    assign: Object.assign(assign, assign),
    complete: Object.assign(complete, complete),
    deliver: Object.assign(deliver, deliver),
}

export default orders