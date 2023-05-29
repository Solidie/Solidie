import React from 'react'
import SalesTable from '../components/sales/SalesTable.jsx'
import { Link } from 'react-router-dom'

const Sales = () => {
  return (
    <div className="flex flex-col w-full h-full">
      {/* Header */}
      <div className="flex justify-between items-center w-full">
        <h1 className="text-3xl font-bold">Sales</h1>
      </div>
      {/* Apps Table */}
      <SalesTable />
    </div>
  )
}

export default Sales