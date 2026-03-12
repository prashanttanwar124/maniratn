import DashboardController from './DashboardController'
import ExpenseController from './ExpenseController'
import OrderController from './OrderController'
import ProductController from './ProductController'
import CustomerController from './CustomerController'
import MortgageController from './MortgageController'
import InvoiceController from './InvoiceController'
import SupplierController from './SupplierController'
import LedgerController from './LedgerController'
import TransactionController from './TransactionController'
import MetalTransactionController from './MetalTransactionController'
import Settings from './Settings'

const Controllers = {
    DashboardController: Object.assign(DashboardController, DashboardController),
    ExpenseController: Object.assign(ExpenseController, ExpenseController),
    OrderController: Object.assign(OrderController, OrderController),
    ProductController: Object.assign(ProductController, ProductController),
    CustomerController: Object.assign(CustomerController, CustomerController),
    MortgageController: Object.assign(MortgageController, MortgageController),
    InvoiceController: Object.assign(InvoiceController, InvoiceController),
    SupplierController: Object.assign(SupplierController, SupplierController),
    LedgerController: Object.assign(LedgerController, LedgerController),
    TransactionController: Object.assign(TransactionController, TransactionController),
    MetalTransactionController: Object.assign(MetalTransactionController, MetalTransactionController),
    Settings: Object.assign(Settings, Settings),
}

export default Controllers