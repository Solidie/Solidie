import React from 'react'
import { DropdownMenuTrigger } from "@radix-ui/react-dropdown-menu"
import { SlidersHorizontal } from "lucide-react"

import { Button } from "../index"
import {
  DropdownMenu,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuSeparator,
} from "../dropdown-menu.jsx"


export function DataTableViewOptions({
  table,
}) {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          variant="outline"
          size="sm"
          className={"ml-auto hidden h-8 lg:flex".classNames()}
        >
          <SlidersHorizontal className={"mr-2 h-4 w-4".classNames()} />
          View
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className={"w-[150px]".classNames()}>
        <DropdownMenuLabel>Toggle columns</DropdownMenuLabel>
        <DropdownMenuSeparator />
        {table
          .getAllColumns()
          .filter(
            (column) =>
              typeof column.accessorFn !== "undefined" && column.getCanHide()
          )
          .map((column) => {
            return (
              <DropdownMenuCheckboxItem
                key={column.id}
                className={"capitalize".classNames()}
                checked={column.getIsVisible()}
                onCheckedChange={(value) => column.toggleVisibility(!!value)}
              >
                {column.id}
              </DropdownMenuCheckboxItem>
            )
          })}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}