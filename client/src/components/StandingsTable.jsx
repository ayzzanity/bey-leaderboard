import { Table } from 'antd';

export default function StandingsTable({ standings }) {
  return (
    <Table
      dataSource={standings}
      columns={[
        { title: 'Rank', dataIndex: 'swiss_rank' },
        {
          title: 'Player',
          dataIndex: 'player.name',
          render: (_, record) => `${record.player.name}`
        },
        { title: 'Wins', dataIndex: 'swiss_wins' },
        { title: 'Losses', dataIndex: 'swiss_losses' }
      ]}
      pagination={{ pageSize: 99 }}
    />
  );
}
