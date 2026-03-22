import { Table } from 'antd';

export default function StandingsTable({ standings, columns, rowKey = 'id', rowClassName }) {
  return (
    <Table
      dataSource={standings}
      columns={columns}
      rowKey={rowKey}
      rowClassName={rowClassName}
      pagination={{ pageSize: 99 }}
    />
  );
}
